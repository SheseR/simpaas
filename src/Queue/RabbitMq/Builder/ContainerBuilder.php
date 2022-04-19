<?php
namespace Levtechdev\Simpaas\Queue\RabbitMq\Builder;

use Levtechdev\Simpaas\Helper\Logger;
use Levtechdev\Simpaas\Queue\RabbitMq\Container;
use Levtechdev\Simpaas\Queue\RabbitMq\Entity\ExchangeEntity;
use Levtechdev\Simpaas\Queue\RabbitMq\Connection\AMQPConnection;
use Levtechdev\Simpaas\Queue\RabbitMq\Entity\QueueEntity;
use RuntimeException;

class ContainerBuilder
{
    public function __construct(protected Logger $loggerHelper)
    {

    }

    /**
     * @param array $config
     *
     * @return Container
     *
     * @throws \Exception
     */
    public function createContainer(array $config): Container
    {
        $connections = $this->createConnections($config['connections']);
        $exchanges = $this->createExchanges($config['exchanges'], $connections);
        $queues = $this->createQueues($config['queues'], $connections);

        $container = new Container();

        // Publisher can be defined to exchange or queue
        foreach ($config['publishers'] as $publisherAliasName => $publisherEntityBind) {
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

            $container->addPublisher($publisherAliasName, $entity);
        }

        foreach ($config['consumers'] as $consumerAliasName => $consumerDetails) {
            $messageProcessor = $consumerDetails['message_processor'];
            if (!array_key_exists($consumerDetails['queue'], $queues)) {
                throw new \RuntimeException(
                    sprintf(
                        "Cannot create consumer %s: no queue named %s defined!",
                        (string)$consumerAliasName,
                        (string)$consumerDetails['queue']
                    )
                );
            }

            /** @var QueueEntity $entity */
            $entity = $queues[$consumerDetails['queue']];
            $entity->setPrefetchCount($consumerDetails['prefetch_count']);
            $entity->setIdleTtl($consumerDetails['idle_ttl'] ?? 0);
            $entity->setMessageProcessor($messageProcessor);

            $entity->setLogger($this->loggerHelper->getLogger(
                'queue', base_path(Logger::LOGS_DIR . $consumerDetails['log_file']))
            );

            $container->addConsumer($consumerAliasName, $entity);
        }

        return $container;
    }

    /**
     * @param array $connectionsConfig
     *
     * @return array
     */
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

    /**
     * @param array $queueConfigList
     * @param array $connections
     *
     * @return array
     */
    protected function createQueues(array $queueConfigList, array $connections): array
    {
        $queues = [];
        foreach ($queueConfigList as $queueAliasName => $queueDetails) {
            // verify if the connection exists
            if (array_key_exists('connection', $queueDetails) &&
                !array_key_exists($queueDetails['connection'], $connections)
            ) {
                throw new \RuntimeException(
                    sprintf(
                        "Could not create exchange %s: connection name %s is not defined!",
                        (string)$queueAliasName,
                        (string)$queueDetails['connection']
                    )
                );
            }

            $queues[$queueAliasName] = QueueEntity::createQueue(
                $connections[$queueDetails['connection']],
                $queueAliasName,
                array_merge(
                    $queueDetails['attributes'],
                    ['name' => $queueDetails['name']],
                    ['retry_queue' => $queueDetails['retry_queue'] ?? []],
                )
            );
        }

        return $queues;
    }
}