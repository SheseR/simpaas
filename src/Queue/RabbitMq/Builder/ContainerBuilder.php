<?php
namespace Levtechdev\Simpaas\Queue\RabbitMq\Builder;

use Levtechdev\Simpaas\Queue\RabbitMq\Container;
use Levtechdev\Simpaas\Queue\RabbitMq\Entity\ExchangeEntity;
use Levtechdev\Simpaas\Queueu\RabbitMq\Connection\AMQPConnection;
use RuntimeException;

class ContainerBuilder
{
    public function createContainer(array $config): Container
    {
        $connections = $this->createConnections($config['connections']);
        $exchanges = $this->createExchanges($config['exchanges'], $connections);
        $queues = [];
        $container = new Container();
        foreach ($config['publishers'] as $publisherAliasName => $publisherEntityBind) {
           // @todo
            if (empty($publisherEntityBind)) {
                continue;
            }

            if (array_key_exists($publisherEntityBind, $exchanges)) {
                $entity = $exchanges[$publisherEntityBind];
            } elseif (array_key_exists($publisherEntityBind, $queues)) {
                $entity = $queues[$publisherEntityBind];
            } else {
                throw new \RuntimeException(
                    sprintf(
                        "Cannot create publisher %s: no exchange or queue named %s defined!",
                        (string)$publisherAliasName,
                        (string)$publisherEntityBind
                    )
                );
            }

            $container->addPublisher(
                $publisherAliasName,
                $entity
            );
        }

        return $container;
    }

    protected function createConnections(array $connectionsConfig): array
    {
        $connections = [];
        foreach ($connectionsConfig as $connectionAliasName => $connectionCredentials) {
            $connections[$connectionAliasName] =
                AMQPConnection::createConnection($connectionAliasName, $connectionCredentials);
        }

        return $connections;
    }

    /**
     * @param array $exchangesConfig
     * @param array $connections
     *
     * @return array
     */
    protected function createExchanges(array $exchangesConfig, array $connections): array
    {
        $exchanges = [];
        foreach ($exchangesConfig as $exchangeAliasName => $exchangeDetails) {
            // verify if the connection exists
            if (array_key_exists('connection', $exchangeDetails) &&
                !key_exists($exchangeDetails['connection'], $connections) ) {

                throw new RuntimeException(
                    sprintf(
                        "Could not create exchange %s: connection name %s is not defined!",
                        (string)$exchangeAliasName,
                        (string)$exchangeDetails['connection']
                    )
                );
            }

            $exchanges[$exchangeAliasName] =
                ExchangeEntity::createExchange(
                    $connections[$exchangeDetails['connection']],
                    $exchangeAliasName,
                    array_merge($exchangeDetails['attributes'], ['name' => $exchangeDetails['name']])
                );
        }
        return $exchanges;

    }
}