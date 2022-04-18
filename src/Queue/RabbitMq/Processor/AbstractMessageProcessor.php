<?php

namespace Levtechdev\Simpaas\Queue\RabbitMq\Processor;

use Levtechdev\Simpaas\Queue\ServiceInterface;
use PhpAmqpLib\Message\AMQPMessage;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

abstract class AbstractMessageProcessor implements MessageProcessorInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * @const string Key used on message to identify if we ack/nack via the child
     */
    const HANDLED_PROPERTY = 'handled_property';

    /**
     * @var int
     */
    private $messageCount = 0;

    protected array $deliveryTagMessages = [];
    protected array $deliveryTagTransferData = [];

    public function __construct(protected ServiceInterface $service)
    {
        $this->service->setLogger($this->logger);
    }

    /**
     * @todo not implemented
     * @param AMQPMessage $message
     *
     * @return void
     */
    public function consume(AMQPMessage $message)
    {
        $this->messageCount++;
        try {
            $response = $this->processMessage($message);
            // Already ack/nack from inside the processor using
            // the protected methods ::ack / ::nack
            if (property_exists($message, self::HANDLED_PROPERTY)) {
               // $this->logger->debug("Already handled!");
                return;
            }
            if ($response === true) {
                $this->ack($message);
            } else {
                $this->nack($message);
            }
        } catch (\Throwable $e) {
            $this->logger->error(
                sprintf(
                    "Could not process message, got %s from %s in %d for message: %s",
                    get_class($e) . '-' . $e->getMessage(),
                    (string)$e->getFile(),
                    (int)$e->getLine(),
                    (string)$message->getBody()
                )
            );
            $this->nack($message);
        }
    }

    /**
     * @param array $messages
     *
     * @return mixed
     */
    public function batchConsume(array $messages): mixed
    {
        $this->deliveryTagTransferData = [];
        if (empty($messages)) {

            return 1;
        }

        $this->messageCount = count($messages);

        /**
         * @var  $deliveryTag
         * @var AMQPMessage $message
         */
        foreach ($messages as $deliveryTag => $message) {
            $this->deliveryTagTransferData[$deliveryTag] = json_decode($message->getBody());
        }
        $lastDeliveryTag = array_key_last($messages);

        try {
            $serviceResult = $this->service->execute($this->deliveryTagTransferData);
            // 1 check if we can use multiple acknowelege
            // 2. check for rejecting or redelivering
            // @todo

        } catch (\Throwable $e) {
            $this->nack($messages[$lastDeliveryTag], true);

            return 1;
        }

        $this->ack($messages[$lastDeliveryTag], true);

        return 1;
    }

    /**
     * @param AMQPMessage $message
     * @param bool $multiple
     *
     * @return void
     */
    protected function ack(AMQPMessage $message, bool $multiple = false)
    {
        try {
            $message->getChannel()->basic_ack($message->getDeliveryTag(), $multiple);
            $message->{self::HANDLED_PROPERTY} = true;
        } catch (\Throwable $e) {
            $this->logger->error(
                sprintf(
                    "Could not process message, got %s from %s in %d for message: %s",
                    get_class($e) . '-' . $e->getMessage(),
                    (string)$e->getFile(),
                    (int)$e->getLine(),
                    (string)$message->getBody()
                )
            );
        }
    }

    /**
     * @param AMQPMessage $message
     * @param bool $redeliver
     */
    protected function nack(AMQPMessage $message, bool $multiple = false, bool $redeliver = true)
    {
        try {
            $message->getChannel()->basic_nack($message->getDeliveryTag(), $multiple, $redeliver);
            $message->{self::HANDLED_PROPERTY} = true;
        } catch (\Throwable $e) {
            $this->logger->debug(sprintf("Did not processed with success message %s", $message->getBody()));
        }
    }

    /**
     * @return int
     */
    public function getProcessedMessages(): int
    {
        return $this->messageCount;
    }

    /**
     * @param AMQPMessage $message
     * @return bool
     */
    abstract public function processMessage(AMQPMessage $message): bool;
}