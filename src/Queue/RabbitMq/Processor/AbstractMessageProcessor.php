<?php

namespace Levtechdev\Simpaas\Queue\RabbitMq\Processor;

use Levtechdev\Simpaas\Service\Logger\DebugLogTrait;
use PhpAmqpLib\Message\AMQPMessage;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

abstract class AbstractMessageProcessor implements MessageProcessorInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;
    use DebugLogTrait;

    public function __construct()
    {
        $this->setIsDebugLevel();
    }

    /**
     * @const string Key used on message to identify if we ack/nack via the child
     */
    const HANDLED_PROPERTY = 'handled_property';

    /**
     * @var int
     */
    private $messageCount = 0;

    /**
     * @todo not implemented
     *
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
        if (empty($messages)) {

            return 1;
        }

        $this->messageCount = count($messages);

        $deliveryTagTransferData = [];
        /**
         * @var  $deliveryTag
         * @var AMQPMessage $message
         */
        foreach ($messages as $deliveryTag => $message) {
            $deliveryTagTransferData[$deliveryTag] = json_decode($message->getBody());
        }

        try {
            $lastDeliveryTag = array_key_last($messages);

            $this->debug(
                sprintf('Received %d messages ', count($messages)), [
                    'class'=> get_called_class(),
                    'messages' => $deliveryTagTransferData
                ]
            );

            $t = microtime(true);
            $serviceResult = $this->processTransferMessages($deliveryTagTransferData);
            $this->debug(
                sprintf('Service processed in %ss ', microtime(true) - $t), [
                    'class'=> get_called_class(),
                    'result' => $deliveryTagTransferData
                ]
            );

            $multiple = true;
            $prevStatus = null;
            foreach($serviceResult as $deliveryTag => $result) {
                if ($prevStatus != null && $prevStatus != $result['status']) {
                    $multiple = false;
                    break;
                }

                $prevStatus = $result['status'];
            }
            // @todo check for rejecting or redelivering (use enum from 8.1)
        } catch (\Throwable $e) {
            $this->nack($messages[$lastDeliveryTag], true);

            return 1;
        }

        if ($multiple) {
            $this->ack($messages[$lastDeliveryTag], true);

            return 1;
        }

        foreach ($deliveryTagTransferData as $deliveryTag => $deliveryTagTransferDatum) {
            if ($deliveryTagTransferDatum['status']) {
                $this->ack($messages[$deliveryTag]);

                continue;
            }

            $this->nack($messages[$deliveryTag]);
        }

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
            $t = microtime(true);
            $message->getChannel()->basic_ack($message->getDeliveryTag(), $multiple);
            $message->{self::HANDLED_PROPERTY} = true;

            $this->debug(
                sprintf('Message Ack in %s', microtime(true) - $t), [
                    'class'=> get_called_class(),
                    'multiple' => $multiple,
                    'delivery-tag' => $message->getDeliveryTag(),
                    'message' => $message->getBody()
                ]
            );

        } catch (\Throwable $e) {
            $this->logger->error($e->getMessage(), [
                'message' => $message->getBody(),
                'trace' => $e->getTraceAsString(),
                'multiple' => $multiple
            ]);
        }
    }

    /**
     * @param AMQPMessage $message
     * @param bool $multiple
     * @param bool $redeliver
     * @return void
     */
    protected function nack(AMQPMessage $message, bool $multiple = false, bool $redeliver = true)
    {
        try {
            $t = microtime(true);
            $message->getChannel()->basic_nack($message->getDeliveryTag(), $multiple, $redeliver);
            $message->{self::HANDLED_PROPERTY} = true;

            $this->debug(
                sprintf('Message Nack in %s', microtime(true) - $t), [
                    'class'=> get_called_class(),
                    'multiple' => $multiple,
                    'redeliver' => $redeliver,
                    'delivery-tag' => $message->getDeliveryTag(),
                    'message' => $message->getBody()
                ]
            );
        } catch (\Throwable $e) {
            $this->logger->error($e->getMessage(), [
                'message' => $message->getBody(),
                'trace' => $e->getTraceAsString(),
                'multiple' => $multiple
            ]);
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
    abstract protected function processMessage(AMQPMessage $message): bool;

    /**
     * @param array $batchTransferMessages
     *
     * @return array
     */
    abstract protected function processTransferMessages(array $batchTransferMessages): array;
}