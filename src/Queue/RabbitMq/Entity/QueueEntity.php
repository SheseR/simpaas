<?php

namespace Levtechdev\Simpaas\Queue\RabbitMq\Entity;

use JetBrains\PhpStorm\ArrayShape;
use Levtechdev\Simpaas\Helper\Logger;
use Levtechdev\Simpaas\Queue\RabbitMq\Connection\AMQPConnection;
use Levtechdev\Simpaas\Queue\RabbitMq\ConsumerInterface;
use Levtechdev\Simpaas\Queue\RabbitMq\MessageInterface;
use Levtechdev\Simpaas\Queue\RabbitMq\Processor\AbstractMessageProcessor;
use Levtechdev\Simpaas\Queue\RabbitMq\Processor\MessageProcessorInterface;
use Levtechdev\Simpaas\Queue\RabbitMq\PublisherInterface;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Exception\AMQPProtocolChannelException;
use PhpAmqpLib\Exception\AMQPTimeoutException;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerTrait;

class QueueEntity implements PublisherInterface, ConsumerInterface, AMQPEntityInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * @const int   Retry count when a Channel Closed exeption is thrown
     */
    const MAX_RETRIES = 3;

    /**
     * @const array Default connections parameters
     */
    const DEFAULTS = [
        // Whether to check if it exists or to verify existance using argument types (Throws PRECONDITION_FAILED)
        'passive'                      => false,
        // Entities with durable will be re-created uppon server restart
        'durable'                      => false,
        // whether to use it by only one channel, then it gets deleted
        'exclusive'                    => false,
        // Whether to delete it when the queue has no event on it
        'auto_delete'                  => false,
        // Whether to receive a Declare confirmation
        'nowait'                       => false,
        // Additional arguments for queue creation
        'arguments'                    => [],
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
     * @var AMQPConnection
     */
    protected $connection;

    /**
     * @var string
     */
    protected string $aliasName;

    /**
     * @var array
     */
    protected array $attributes = [];

    /**
     * @var int
     */
    protected int $prefetchCount = 1;

    /**
     * @var null|string|MessageProcessorInterface
     */
    protected $messageProcessor = null;

    /**
     * @var int
     */
    protected int $limitMessageCount;

    /**
     * @var int
     */
    protected int  $limitSecondsUptime;

    /**
     * @var int
     */
    protected int $limitMemoryConsumption;

    /**
     * @var double
     */
    protected $startTime = 0;

    /**
     * @var int
     */
    protected $retryCount = 0;

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
     * @param array $queueDetails
     * @return QueueEntity
     */
    public static function createQueue(AMQPConnection $connection, string $aliasName, array $queueDetails): static
    {
        return new static(
            $connection,
            $aliasName,
            array_merge(self::DEFAULTS, $queueDetails)
        );
    }

    /**
     * @return array|null
     */
    public function getQueueInfo(): array|null
    {
        return $this->getChannel()->queue_declare($this->attributes['name'], true);
    }

    /**
     * @return string
     */
    public function getQueueAliasName(): string
    {
        return $this->attributes['name'];
    }

    /**
     * @return string
     */
    public function getAliasName(): string
    {
        return $this->aliasName;
    }

    /**
     * ExchangeEntity constructor.
     *
     * @param AMQPConnection $connection
     * @param string $aliasName
     * @param array $attributes
     */
    public function __construct(AMQPConnection $connection, string $aliasName, array $attributes = [])
    {
        $this->connection = $connection;
        $this->aliasName  = $aliasName;
        $this->attributes = $attributes;
    }

    /**
     * @param int $prefetchCount
     *
     * @return ConsumerInterface
     */
    public function setPrefetchCount(int $prefetchCount): ConsumerInterface
    {
        $this->prefetchCount = $prefetchCount;

        return $this;
    }

    /**
     * @param string $messageProcessor
     * @return ConsumerInterface
     */
    public function setMessageProcessor(string $messageProcessor): ConsumerInterface
    {
        $this->messageProcessor = $messageProcessor;

        return $this;
    }

    /**
     * @return AMQPConnection
     */
    protected function getConnection(): AMQPConnection
    {
        return $this->connection;
    }

    /**
     * @return AMQPChannel
     */
    protected function getChannel(): AMQPChannel
    {
        return $this->getConnection()->getChannel();
    }

    /**
     * Create the Queue
     */
    public function create(): void
    {
        try {
            $this->getChannel()
                ->queue_declare(
                    $this->attributes['name'],
                    $this->attributes['passive'],
                    $this->attributes['durable'],
                    $this->attributes['exclusive'],
                    $this->attributes['auto_delete'],
                    $this->attributes['nowait'],
                    new AMQPTable($this->attributes['arguments'])
                );
        } catch (AMQPProtocolChannelException $e) {
            // 406 is a soft error triggered for precondition failure (when redeclaring with different parameters)
            if (true === $this->attributes['throw_exception_on_redeclare'] || $e->amqp_reply_code !== 406) {
                throw $e;
            }
            // a failure trigger channels closing process
            $this->reconnect();
        }

        if (!empty($this->attributes['retry_queue'])) {
            try {
                $this->getChannel()
                    ->queue_declare(
                        $this->attributes['retry_queue']['name'],
                        false,
                        $this->attributes['retry_queue']['durable'] ?? true,
                        false,
                        false,
                        false,
                        new AMQPTable($this->attributes['retry_queue']['arguments'])
                    );
            } catch (AMQPProtocolChannelException $e) {
                // 406 is a soft error triggered for precondition failure (when redeclaring with different parameters)
                if (true === $this->attributes['throw_exception_on_redeclare'] || $e->amqp_reply_code !== 406) {
                    throw $e;
                }
                // a failure trigger channels closing process
                $this->reconnect();
            }
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
                        $this->attributes['name'],
                        $bindItem['exchange'],
                        $bindItem['routing_key']
                    );
            } catch (AMQPProtocolChannelException $e) {
                // 404 is the code for trying to bind to an non-existing entity
                if (true === $this->attributes['throw_exception_on_bind_fail'] || $e->amqp_reply_code !== 404) {
                    throw $e;
                }
                $this->reconnect();
            }
        }
    }

    /**
     * Delete the queue
     */
    public function delete(): void
    {
        $this->getChannel()->queue_delete($this->attributes['name']);
    }

    /**
     * {@inheritdoc}
     */
    public function reconnect(): void
    {
        $this->getConnection()->reconnect();
    }

    /**
     * @param int $messages
     * @param int $seconds
     * @param int $maxMemory
     *
     * @return int
     *
     * @throws AMQPProtocolChannelException
     */
    public function startConsuming(int $messages, int $seconds, int $maxMemory): int
    {
        dd($this->logger);
        $this->logger->warning('start    ');
        $this->setupConsumer($messages, $seconds, $maxMemory);
        while (false === $this->shouldStopConsuming()) {
            if (count($this->inputBatchMessages) >= $this->prefetchCount) {
                $this->getMessageProcessor()->batchConsume($this->inputBatchMessages);
                $this->inputBatchMessages = [];
            }

            try {
                $this->getChannel()->wait(null, false, 1);
            } catch (AMQPTimeoutException $e) {
                usleep(1000);
                $this->getConnection()->reconnect();
                $this->setupChannelConsumer();
            } catch (\Throwable $e) {
                // stop the consumer
                $this->stopConsuming();
                dump($e->getMessage(), $e->getTraceAsString());
//                $this->logger->notice(sprintf(
//                    "Stopped consuming: %s in %s:%d",
//                    get_class($e) . ' - ' . $e->getMessage(),
//                    (string)$e->getFile(),
//                    (int)$e->getLine()
//                ));
                return 1;
            }
        }
        return 0;
    }

    /**
     * @return bool
     */
    protected function shouldStopConsuming(): bool
    {
        if ((microtime(true) - $this->startTime) > $this->limitSecondsUptime) {
            $this->logger->debug(
                "Stopped consumer",
                [
                    'limit' => 'time_limit',
                    'value' => sprintf("%.2f", microtime(true) - $this->startTime)
                ]
            );

            return true;
        }
        if (memory_get_peak_usage(true) >= ($this->limitMemoryConsumption * 1048576)) {
            $this->logger->debug(
                "Stopped consumer",
                [
                    'limit' => 'memory_limit',
                    'value' => (int)round(memory_get_peak_usage(true) / 1048576, 2)
                ]
            );

            return true;
        }

        if ($this->getMessageProcessor()->getProcessedMessages() >= $this->limitMessageCount) {
            $this->logger->debug(
                "Stopped consumer",
                ['limit' => 'message_count', 'value' => (int)$this->getMessageProcessor()->getProcessedMessages()]
            );
            return true;
        }
        return false;
    }

    /**
     * Stop the consumer
     */
    public function stopConsuming()
    {
        try {
            $this->getChannel()->basic_cancel($this->getConsumerTag(), false, true);
        } catch (\Throwable $e) {
            $this->logger->notice("Got " . $e->getMessage() . " of type " . get_class($e));
        }
    }

    /**
     * Handle shutdown - Usually in case "Allowed memory size of x bytes exhausted"
     */
    private function registerShutdownHandler()
    {
        $consumer = $this;
        register_shutdown_function(function () use ($consumer) {
            $consumer->stopConsuming();
        });
    }

    /**
     * Register signals
     */
    protected function handleKillSignals()
    {
        if (extension_loaded('pcntl')) {
            pcntl_signal(SIGTERM, [$this, 'catchKillSignal']);
            pcntl_signal(SIGINT, [$this, 'catchKillSignal']);

            if (function_exists('pcntl_signal_dispatch')) {
                // let the signal go forward
                pcntl_signal_dispatch();
            }
        }
    }

    /**
     * Handle Kill Signals
     * @param int $signalNumber
     */
    public function catchKillSignal(int $signalNumber)
    {
        $this->stopConsuming();
        $this->logger->debug(sprintf("Caught signal %d", $signalNumber));
    }

    /**
     * It is the tag that is listed in RabbitMQ UI as the consumer "name"
     *
     * @return string
     */
    private function getConsumerTag(): string
    {
        return sprintf("%s_%s_%s", $this->aliasName, gethostname(), getmypid());
    }

    /**
     * @return MessageProcessorInterface
     */
    private function getMessageProcessor(): MessageProcessorInterface
    {
        if (!($this->messageProcessor instanceof MessageProcessorInterface)) {
            $this->messageProcessor = app($this->messageProcessor);
            if ($this->messageProcessor instanceof AbstractMessageProcessor) {
                $this->messageProcessor->setLogger($this->logger);
            }
        }
        return $this->messageProcessor;
    }

    /**
     * @param int $messages
     * @param int $seconds
     * @param int $maxMemory
     * @return void
     * @throws AMQPProtocolChannelException
     */
    protected function setupConsumer(int $messages, int $seconds, int $maxMemory)
    {
        $this->limitMessageCount = $messages;
        $this->limitSecondsUptime = $seconds;
        $this->limitMemoryConsumption = $maxMemory;

        $this->startTime = microtime(true);

        $this->setupChannelConsumer();

        $this->registerShutdownHandler();
        $this->handleKillSignals();
    }

    /**
     * @return void
     *
     * @throws AMQPProtocolChannelException
     */
    private function setupChannelConsumer()
    {
        if ($this->attributes['auto_create'] === true) {
            $this->create();
            $this->bind();
        }

        $this->getChannel()
            ->basic_qos(null, $this->prefetchCount, true);

        $this->getChannel()
            ->basic_consume(
                $this->attributes['name'],
                $this->getConsumerTag(),
                false,
                false,
                false,
                false,
                [
                    $this,
                    'addMessageToBatch'
                ]
            );
    }

    protected array $inputBatchMessages = [];
    /**
     * @param AMQPMessage $message
     *
     * @return $this
     */
    public function addMessageToBatch(AMQPMessage $message): self
    {
        $this->inputBatchMessages[$message->getDeliveryTag()] = $message;

        return $this;
    }

    /**
     * @param AMQPMessage $message
     * @throws \Throwable
     */
    public function consume(AMQPMessage $message)
    {
        try {
            $this->getMessageProcessor()->consume($message);
            $this->logger->debug("Consumed message", ['message' => $message->getBody()]);
        } catch (\Throwable $e) {
            $this->logger->notice(
                sprintf(
                    "Got %s from %s in %d",
                    $e->getMessage(),
                    (string)$e->getFile(),
                    (int)$e->getLine()
                )
            );
            // let the exception slide, the processor should handle
            // exception, this is just a notice that should not
            // ever appear
            throw $e;
        }
    }

    /**
     * @param array $rawBatchData
     * @param int $priority
     *
     * @return void
     *
     * @throws AMQPProtocolChannelException
     */
    public function publishBatch(
        #[ArrayShape([[
            'body' => 'array',
            'routing_key' => 'string',
            'priority' => 'string'
        ]])] array $rawBatchData,
        int $priority = MessageInterface::PRIORITY_LOW
    ): void {
        if ($this->attributes['auto_create'] === true) {
            $this->create();
            $this->bind();
        }
        $channel = $this->getChannel();
        foreach ($rawBatchData as $message) {
            $preparedMessage = $this->prepareMessage($message);

            $channel->batch_basic_publish(
                new AMQPMessage($preparedMessage['body'], $preparedMessage['properties']),
                '',
                $this->attributes['name'],
                true
            );
        }

        $channel->publish_batch();
    }

    /**
     * Publish a message
     *
     * @param array $rawData
     * @param string $routingKey
     * @return void
     *
     * @throws AMQPProtocolChannelException
     */
    public function publish(
        #[ArrayShape([
            'body' => 'array',
            'priority' => 'int'
    ])] array $rawData,
        string $routingKey = ''
    ): void {
        if ($this->attributes['auto_create'] === true) {
            $this->create();
            $this->bind();
        }
        $preparedMessage = $this->prepareMessage($rawData);
        try {
            $this->getChannel()
                ->basic_publish(
                    new AMQPMessage($preparedMessage['body'], $preparedMessage['properties']),
                    '',
                    $this->attributes['name'],
                    true
                );
            $this->retryCount = 0;
        } catch (AMQPProtocolChannelException $exception) {
            $this->retryCount++;
            // Retry publishing with re-connect
            if ($this->retryCount < self::MAX_RETRIES) {
                $this->getConnection()->reconnect();
                $this->publish($rawData, $routingKey);

                return;
            }

            throw $exception;
        }
    }

    /**
     * @param array $data
     *
     * @return array
     */
    #[ArrayShape([
        'body' => 'string',
        'properties' => 'array'
    ])]
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