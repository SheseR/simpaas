<?php
namespace Levtechdev\SimPaas;

use Illuminate\Support\ServiceProvider;
use Levtechdev\Simpaas\Queue\RabbitMq\Command\SetupCommand;
use Levtechdev\Simpaas\Queue\RabbitMq\Helper\ConfigHelper;
use Levtechdev\Simpaas\Queue\Builder\ContainerBuilder;
use Levtechdev\Simpaas\Queue\RabbitMq\Container;
use Levtechdev\Simpaas\Queue\RabbitMQ\PublisherInterface;

class RabbitMqProvider extends ServiceProvider
{
    public function register()
    {
        $this->registerContainer();
        $this->registerPublisher();
    }


    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
              SetupCommand::class
            ]);
        }
    }

    public function registerContainer()
    {
        $config = config('queue', []);
        if (!is_array($config)) {
            throw new \RuntimeException(
                "Invalid configuration provided for LaravelRabbitMQ!"
            );
        }

        $configHelper = new ConfigHelper();
        $config = $configHelper->addDefaults($config);
        $this->app->singleton(Container::class, function () use ($config) {
                $container = new ContainerBuilder();
                return $container->createContainer($config);
            }
        );
    }

    public function registerPublisher()
    {
        // Get "tagged" like Publisher
        $this->app->singleton(PublisherInterface::class, function ($application, $arguments) {
            /** @var Container $container */
            $container = $application->make(Container::class);
            if (empty($arguments)) {
                throw new \RuntimeException("Cannot make Publisher. No publisher identifier provided!");
            }
            $aliasName = $arguments[0];
            return $container->getPublisher($aliasName);
        });
    }
}