<?php

namespace Levtechdev\Simpaas;

use Illuminate\Encryption\Encrypter;
use Illuminate\Redis\RedisManager;
use Illuminate\Support\Arr;
use Illuminate\Support\ServiceProvider;
use Levtechdev\Simpaas\Authorization\Helper\Auth;
use Levtechdev\Simpaas\Console\Command\Management\AppInitCommand;
use Levtechdev\Simpaas\Console\Command\Management\KeyGenerateCommand;
use Levtechdev\Simpaas\Console\Command\Management\MaintenanceModeCommand;
use Levtechdev\Simpaas\Console\Command\Management\ResetLogFilesCommand;
use Levtechdev\Simpaas\Helper\Operation;
use Levtechdev\Simpaas\Helper\RandomHash;
use Levtechdev\Simpaas\Helper\SystemInfo;
use Levtechdev\Simpaas\Helper\Logger;

class CoreProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton(RandomHash::class);
        $this->app->singleton(SystemInfo::class);
        $this->app->singleton(Auth::class);
        $this->app->singleton(Logger::class, function ($app) {
            $config = $app->make('config')->get('log');

            return new Logger($config);
        });
        $this->app->singleton(Operation::class);
        if (env('SENSITIVE_DATA_TOKEN') !== null) {
            $this->app->singleton(Encrypter::class, function ($app) {

                return new Encrypter(
                    env('SENSITIVE_DATA_TOKEN'), $app->make('config')->get('app.cipher') ?? 'AES-256-CBC'
                );
            });
        }
    }

    /**
     * @return void
     */
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                AppInitCommand::class,
                KeyGenerateCommand::class,
                MaintenanceModeCommand::class,
                ResetLogFilesCommand::class
            ]);
        }
    }

    /**
     * @return string[]
     */
    public function provides(): array
    {
        return [
            Auth::class,
            RandomHash::class,
            SystemInfo::class,
            Logger::class,
            Operation::class,
            Encrypter::class,
        ];
    }
}