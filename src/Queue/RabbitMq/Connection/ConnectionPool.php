<?php

namespace Levtechdev\Simpaas\Queueu\RabbitMq\Connection;

use Illuminate\Config\Repository;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AbstractConnection;
use PhpAmqpLib\Connection\AMQPLazyConnection;
use PhpAmqpLib\Connection\AMQPStreamConnection;


class ConnectionPool
{
    public const PUBLISHER_CON_NAME = 'publisher';
    public const CONSUMER_CON_NAME  = 'consumer';
    public const DEFAULT_CON_NAME   = 'default';

    /** @var array[AbstractConnection] */
    private array $connections;

    // we want to set multiple publisher channels (per QueueManager instances) to one worker (one connection)
    /** @var array[\PhpAmqpLib\Channel\AMQPChannel] */
    private array $publisherChannels;

    private AMQPChannel $consumerChannel;

    /** @var Repository */
    private Repository $config;

    /**
     * ConnectionPool constructor.
     *
     * @param Repository $config
     */
    public function __construct(Repository $config)
    {
        $this->config = $config;
    }

    /**
     * As a rule It will be used for getting Queue info, so It does not matter which connection is using, but
     * preferring PUBLISHER connection, cos Consumer connection can be closed by consumer (app level)
     *
     * @return AbstractConnection
     */
    public function getConnection(): AbstractConnection
    {
        if (!empty($this->connections[self::PUBLISHER_CON_NAME]) &&
            $this->connections[self::PUBLISHER_CON_NAME]->isConnected()
        ) {

            return $this->connections[self::PUBLISHER_CON_NAME];
        }

        if (!empty($this->connections[self::CONSUMER_CON_NAME]) &&
            $this->connections[self::CONSUMER_CON_NAME]->isConnected()
        ) {

            return $this->connections[self::CONSUMER_CON_NAME];
        }

        $this->initConnection();

        return $this->connections[self::PUBLISHER_CON_NAME];
    }

    /**
     * @param string $connectionName
     *
     * @return void
     *
     * @throws \Exception
     */
    public function closeConnection(string $connectionName = self::CONSUMER_CON_NAME): void
    {
        if (!empty($this->connections[$connectionName])) {
            /** @var  AbstractConnection $connection */
            $connection = $this->connections[$connectionName];

            $connection->close();

            if ($connectionName == self::PUBLISHER_CON_NAME) {
                $this->publisherChannels = [];
            } else {
               unset($this->consumerChannel);
            }

            unset($this->connections[$connectionName]);
        }
    }
    /**
     * @return AbstractConnection
     */
    public function getPublisherConnection(): AbstractConnection
    {
        if (empty($this->connections[self::PUBLISHER_CON_NAME]) ||
            !$this->connections[self::PUBLISHER_CON_NAME]->isConnected()
        ) {
            $this->initConnection();
        }

        return $this->connections[self::PUBLISHER_CON_NAME];
    }

    /**
     * @return AbstractConnection
     */
    public function getConsumerConnection(): AbstractConnection
    {
        if (empty($this->connections[self::CONSUMER_CON_NAME]) ||
            !$this->connections[self::CONSUMER_CON_NAME]->isConnected()
        ) {
            $this->initConnection(self::CONSUMER_CON_NAME);
        }

        return $this->connections[self::CONSUMER_CON_NAME];
    }

    /**
     * @param string     $queueName
     * @param array|null $exchangeConfig
     *
     * @return AMQPChannel
     */
    public function getChannelFromPublisherConnection(string $queueName = 'default', ?array $exchangeConfig = []): AMQPChannel
    {
        // the first: check if current connection is_connected
        if (!empty($this->publisherChannels[$queueName]) &&
            $this->publisherChannels[$queueName]->is_open() &&
            $this->publisherChannels[$queueName]->getConnection()->isConnected()
        ) {

            return $this->publisherChannels[$queueName];
        }

        $channel = $this->getPublisherConnection()->channel();
        if (!empty($exchangeConfig['confirm_select'])) {
            $channel->confirm_select();
        }

        $this->publisherChannels[$queueName] = $channel;

        return $this->publisherChannels[$queueName];
    }

    /**
     * @return AMQPChannel
     */
    public function getChannelFromConsumerConnection(): AMQPChannel
    {
        if (!empty($this->consumerChannel) &&
            $this->consumerChannel->is_open() &&
            $this->consumerChannel->getConnection()->isConnected()
        ) {

            return $this->consumerChannel;
        }

        $this->consumerChannel = $this->getConsumerConnection()->channel();

        return $this->consumerChannel;
    }

    /**
     * @param string $connectionFor
     *
     * @return void
     */
    protected function initConnection(string $connectionFor = self::PUBLISHER_CON_NAME): void
    {
        $this->connections[$connectionFor] = $this->getNewConnection((bool) $this->getConfig('lazy'));
    }

    /**
     * @param string $name
     *
     * @return mixed
     */
    protected function getConfig(string $name): mixed
    {
        return $this->config->get($name);
    }

    /**
     * @param bool $isLazyConnection
     *
     * @return AbstractConnection
     */
    public function getNewConnection(bool $isLazyConnection = true): AbstractConnection
    {
        $connectionClass = AMQPStreamConnection::class;
        if ($isLazyConnection) {
            $connectionClass = AMQPLazyConnection::class;
        }

        return app()->make($connectionClass, [
            'host' => $this->getConfig('host'),
            'port' => $this->getConfig('port'),
            'user' =>  $this->getConfig('user'),
            'password' => $this->getConfig('pass'),
            'vhost' => $this->getConfig('vhost'),
            'insists' => $this->getConfig('insist'),
            'login_method' => $this->getConfig('login_method'),
            'login_response' => $this->getConfig('login_response'),
            'locale' => $this->getConfig('locale'),
            'connection_timeout' => $this->getConfig('connection_timeout'),
            'read_write_timeout' => (int)round(min($this->getConfig('read_timeout'), $this->getConfig('write_timeout'))),
            'context' => null,
            'keepalive' => $this->getConfig('keepalive'),
            'heartbeat' => (int)round($this->getConfig('heartbeat')),
            'channel_rpc_timeout' => $this->getConfig('channel_rpc_timeout'),
        ]);
    }
}
