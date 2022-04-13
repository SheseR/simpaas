<?php
namespace Levtechdev\Simpaas;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\Arr;
use Illuminate\Support\ServiceProvider;
use Levtechdev\Simpaas\Console\Command\Elasticsearch\Create;
use Levtechdev\Simpaas\Console\Command\Elasticsearch\EntitiesList;
use Levtechdev\Simpaas\Console\Command\Elasticsearch\Merge;
use Levtechdev\Simpaas\Console\Command\Elasticsearch\MultichannelInit;
use Levtechdev\Simpaas\Console\Command\Elasticsearch\Reindex;
use Levtechdev\Simpaas\Database\Elasticsearch\Builder\BaseClientBuilder;
use Levtechdev\Simpaas\Database\Elasticsearch\ElasticSearchAdapter;
use Levtechdev\Simpaas\Database\Elasticsearch\Processor\BulkProcessor;
use Levtechdev\Simpaas\Helper\Logger;

class ElasticsearchProvider extends ServiceProvider
{
    /** @var array  */
    protected array $clientBuilderNames = [];
    /** @var array  */
    protected array $connectionNames = [];

    /**
     * @return void
     *
     * @throws BindingResolutionException
     */
    public function register()
    {
        $elasticSearchConfig = $this->app->make('config')->get('database.elasticsearch');
        foreach ($elasticSearchConfig as $connectionName => $connection) {
            $clientBuilderName = sprintf('elasticsearch.%s', $connectionName);
            $this->app->singleton($clientBuilderName, function ($app) {
                return BaseClientBuilder::create();
            });

            $this->clientBuilderNames[] = $clientBuilderName;
            foreach ($connection as $clientName => $config){
                $this->initConnection($config, $clientBuilderName, $clientName);
            }
        }

        $this->app->singleton(ElasticSearchAdapter::class);
        $this->app->singleton(BulkProcessor::class);
    }

    /**
     * @param $config
     * @param string $clientBuilderName
     * @param $clientName
     *
     * @return void
     */
    protected function initConnection($config, string $clientBuilderName, $clientName): void
    {
        $connectionName =  sprintf('%s.%s', $clientBuilderName , $clientName);
        $this->connectionNames[] = $connectionName;

        $this->app->singleton($connectionName, function ($app) use ($config, $clientBuilderName) {
            $logConfig = Arr::get($config, 'logger.channel', 'default');
            /** @var BaseClientBuilder $clientBuilder */
            $clientBuilder = $app[$clientBuilderName];

            $logFile = Arr::get($config, 'logger.log_file', false);
            if ($logFile) {
                $clientBuilder->setLogger($this->getLogger($logConfig, $logFile));
            }

            $clientBuilder->setHosts($config['hosts']);

            if (!empty($config['auth']['user'])) {
                $clientBuilder->setBasicAuthentication($config['auth']['user'], $config['auth']['password']);
            }

            return $clientBuilder->build();
        });
    }

    /**
     * @return void
     */
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                Create::class,
                EntitiesList::class,
                Merge::class,
                MultichannelInit::class,
                Reindex::class,
            ]);
        }
    }

    /**
     * @return array
     */
    public function provides(): array
    {
        return array_merge(
            $this->clientBuilderNames,
            $this->connectionNames, [
            ElasticSearchAdapter::class,
            BulkProcessor::class
        ]);
    }

    /**
     * @param string $channel
     * @param string $logFile
     *
     * @return \Monolog\Logger
     */
    protected function getLogger(string $channel, string $logFile): \Monolog\Logger
    {
        return app(Logger::class)->getLogger($channel, base_path(Logger::LOGS_DIR . $logFile));
    }
}