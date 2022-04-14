<?php

namespace Levtechdev\Simpaas\Queue\RabbitMq\Entity;

use Levtechdev\Simpaas\Queue\RabbitMq\MessageInterface;
use Levtechdev\Simpaas\Queue\RabbitMq\PublisherInterface;
use Levtechdev\Simpaas\Queue\RabbitMq\Connection\AMQPConnection;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Exception\AMQPChannelClosedException;
use PhpAmqpLib\Exception\AMQPProtocolChannelException;
use PhpAmqpLib\Message\AMQPMessage;

class ExchangeEntity implements AMQPEntityInterface, PublisherInterface
{
    const MAX_RETRIES = 3;

    const DEFAULTS = [
        'exchange_type'                => 'topic',
        // Whether to check if it exists or to verify existance using argument types (Throws PRECONDITION_FAILED)
        'passive'                      => false,
        // Entities with durable will be re-created uppon server restart
        'durable'                      => false,
        // Whether to delete it when no queues ar bind to it
        'auto_delete'                  => false,
        // Whether the exchange can be used by a publisher or block it (declared just for internal "wiring")
        'internal'                     => false,
        // Whether to receive a Declare confirmation
        'nowait'                       => false,
        // Whether to auto create the entity before publishing/consuming it
        'auto_create'                  => false,
        // whether to "hide" the exception on re-declare.
        // if the `passive` filter is set true, this is redundant
        'throw_exception_on_redeclare' => true,
        // whether to throw on exception when trying to
        // bind to an in-existent queue/exchange
        'throw_exception_on_bind_fail' => true,
    ];

    /**
     * @var int
     */
    protected int $retryCount = 0;

    public function __construct(
        protected AMQPConnection $connection,
        protected string $aliasName,
        protected array $attributes = []
    ) {
    }

    /**
     * @return array
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * @param AMQPConnection $connection
     * @param string $aliasName
     * @param array $exchangeDetails
     *
     * @return static
     */
    public static function createExchange(AMQPConnection $connection, string $aliasName, array $exchangeDetails): static
    {
        return new static(
            $connection,
            $aliasName,
            array_merge(self::DEFAULTS, $exchangeDetails)
        );
    }

    /**
     * @return AMQPConnection
     */
    public function getConnection(): AMQPConnection
    {
        return $this->connection;
    }

    /**
     * @return AMQPChannel
     */
    public function getChannel(): AMQPChannel
    {
        return $this->getConnection()->getChannel();
    }

    /**
     * @return void
     *
     * @throws AMQPProtocolChannelException
     */
    public function create(): void
    {
        try {
            $this->getChannel()
                ->exchange_declare(
                    $this->attributes['name'],
                    $this->attributes['exchange_type'],
                    $this->attributes['passive'],
                    $this->attributes['durable'],
                    $this->attributes['auto_delete'],
                    $this->attributes['internal'],
                    $this->attributes['nowait']
                );
        } catch (AMQPProtocolChannelException $e) {
            // 406 is a soft error triggered for precondition failure (when redeclaring with different parameters)
            if (true === $this->attributes['throw_exception_on_redeclare'] || $e->amqp_reply_code !== 406) {
                throw $e;
            }
            // a failure trigger channels closing process
            $this->getConnection()->reconnect();
        }
    }

    /**
     * @return void
     *
     * @throws AMQPProtocolChannelException
     */
    public function bind(): void
    {
        if (!isset($this->attributes['bind']) || empty($this->attributes['bind'])) {
            return;
        }

        foreach ($this->attributes['bind'] as $bindItem) {

            try {
                $this->getChannel()
                    ->queue_bind(
                        $bindItem['queue'],
                        $this->attributes['name'],
                        $bindItem['routing_key']
                    );
            } catch (AMQPProtocolChannelException $e) {
                // 404 is the code for trying to bind to an non-existing entity
                if (true === $this->attributes['throw_exception_on_bind_fail'] || $e->amqp_reply_code !== 404) {
                    throw $e;
                }
                $this->getConnection()->reconnect();
            }
        }
    }

    /**
     * @return void
     */
    public function delete(): void
    {
        $this->getChannel()->exchange_delete($this->attributes['name']);
    }

    /**
     * @return string
     */
    public function getAliasName(): string
    {
        return $this->aliasName;
    }

    /**
     * Reconnect the entity
     */
    public function reconnect(): void
    {
        $this->getConnection()->reconnect();
    }

    /**
     * @param array $rawMassage
     * @param string $routingKey
     *
     * @return void
     *
     * @throws AMQPProtocolChannelException
     */
    public function publish(array $rawMassage, string $routingKey = ''): void
    {
        if ($this->attributes['auto_create'] === true) {
            $this->create();
            $this->bind();
        }
        $preparedMessage = $this->prepareMessage($rawMassage);

        try {
            $this->getChannel()->basic_publish(
                new AMQPMessage($preparedMessage['body'], $preparedMessage['properties']),
                $this->attributes['name'],
                $routingKey,
                true
            );
            $this->retryCount = 0;
        } catch (AMQPChannelClosedException $exception) {
            $this->retryCount++;
            // Retry publishing with re-connect
            if ($this->retryCount < self::MAX_RETRIES) {
                $this->getConnection()->reconnect();
                $this->publish($rawMassage, $routingKey);

                return;
            }
            throw $exception;
        }
    }

    /**
     * @param array $inputData
     * @param int $priority
     *
     * @return void
     *
     * @throws AMQPProtocolChannelException
     */
    public function publishBatch(array $inputData, int $priority = MessageInterface::PRIORITY_LOW): void
    {
        if ($this->attributes['auto_create'] === true) {
            $this->create();
            $this->bind();
        }
        $channel = $this->getChannel();
        foreach ($inputData as $message) {
            $preparedMessage = $this->prepareMessage($message);

            $channel->batch_basic_publish(
                new AMQPMessage($preparedMessage['body'], $preparedMessage['properties']),
                $this->attributes['name'],
                $message['routing_key'],
                true
            );
        }

        $channel->publish_batch();
    }


    /**
     * @param array $data
     *
     * @return array
     */
    public function prepareMessage(array $data): array
    {
        $res = [];
        $res['body'] = is_array($data['body']) ? $data['body'] : json_encode($data);
        $res['properties'] = [
            'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
            'priority'      => $data['priority'] ?? MessageInterface::PRIORITY_LOW
        ];

        return $res;
    }
}