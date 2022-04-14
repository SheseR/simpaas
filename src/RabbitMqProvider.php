<?php
namespace Levtechdev\SimPaas;

use Illuminate\Support\ServiceProvider;
use Levtechdev\Simpaas\Queue\RabbitMQ\Helper\ConfigHelper;
use Levtechdev\Simpaas\Queue\Builder\ContainerBuilder;
use Levtechdev\Simpaas\Queue\RabbitMQ\Container;

class RabbitMqProvider extends ServiceProvider
{
    public function register()
    {
        $this->registerContainer();
    }


    public function boot()
    {
        $this->publishes([
            realpath(
                dirname(__FILE__)
            ) . '/../config/queue.php' => config_path('queue.php'),
        ]);
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

    }
}