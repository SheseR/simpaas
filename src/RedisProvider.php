<?php

namespace Levtechdev\SimPaas;

use Illuminate\Redis\RedisManager;
use Illuminate\Support\Arr;
use Illuminate\Support\ServiceProvider;
use Levtechdev\SimPaas\Database\Redis\RedisAdapter;

class RedisProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton(RedisAdapter::class, function ($app) {
            $config = $app->make('config')->get('database.redis', []);

            return new RedisAdapter($app, Arr::pull($config, 'client', 'phpredis'), $config);
        });

        $this->app->singleton('redis', function ($app) {
            $config = $app->make('config')->get('database.redis', []);

            return new RedisAdapter($app, Arr::pull($config, 'client', 'phpredis'), $config);
        });

        $this->app->bind('redis.connection', function ($app) {
            return $app['redis']->connection();
        });
    }

    /**
     * @return string[]
     */
    public function provides(): array
    {
        return [
            'redis',
            'redis.connection',
            RedisAdapter::class
        ];
    }
}