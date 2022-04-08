<?php

namespace Levtechdev\SimPaas;

use Illuminate\Encryption\Encrypter;
use Illuminate\Redis\RedisManager;
use Illuminate\Support\Arr;
use Illuminate\Support\ServiceProvider;
use Levtechdev\SimPaas\Authorization\Helper\Auth;
use Levtechdev\SimPaas\Console\Command\Management\AppInitCommand;
use Levtechdev\SimPaas\Console\Command\Management\KeyGenerateCommand;
use Levtechdev\SimPaas\Console\Command\Management\MaintenanceModeCommand;
use Levtechdev\SimPaas\Console\Command\Management\ResetLogFilesCommand;
use Levtechdev\SimPaas\Helper\Operation;
use Levtechdev\SimPaas\Helper\RandomHash;
use Levtechdev\SimPaas\Helper\SystemInfo;
use Levtechdev\SimPaas\Helper\Logger;

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