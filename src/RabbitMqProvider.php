<?php
namespace Levtechdev\SimPaas;

use Illuminate\Support\ServiceProvider;
use Levtechdev\Simpaas\Queue\RabbitMq\Command\SetupCommand;
use Levtechdev\Simpaas\Queue\RabbitMq\ConsumerInterface;
use Levtechdev\Simpaas\Queue\RabbitMq\Helper\ConfigHelper;
use Levtechdev\Simpaas\Queue\RabbitMq\Builder\ContainerBuilder;
use Levtechdev\Simpaas\Queue\RabbitMq\Container;
use Levtechdev\Simpaas\Queue\RabbitMQ\PublisherInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;

class RabbitMqProvider extends ServiceProvider
{
    public function register()
    {
        $this->registerContainer();
        $this->registerPublisher();
        $this->registerConsumers();
    }

    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
              SetupCommand::class
            ]);
        }
    }

    /**
     * @return void
     */
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

    /**
     * @return void
     */
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

    public function registerConsumers()
    {
        $this->app->singleton(ConsumerInterface::class, function ($application, $arguments) {
            /** @var Container $container */
            $container = $application->make(Container::class);
            if (empty($arguments)) {
                throw new \RuntimeException("Cannot make Consumer. No consumer identifier provided!");
            }
            $aliasName = $arguments[0];

            if (!$container->hasConsumer($aliasName)) {
                throw new \RuntimeException("Cannot make Consumer.\nNo consumer with alias name {$aliasName} found!");
            }
            /** @var LoggerAwareInterface $consumer */
            $consumer = $container->getConsumer($aliasName);
            $consumer->setLogger($application->make(LoggerInterface::class));

            return $consumer;
        });
    }
}