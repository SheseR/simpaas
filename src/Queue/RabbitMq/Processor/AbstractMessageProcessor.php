<?php

namespace Levtechdev\Simpaas\Queue\RabbitMq\Processor;

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

    /**
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
                dump("Already handled!");
               // $this->logger->debug("Already handled!");
                return;
            }
            if ($response === true) {
                $this->ack($message);
            } else {
                $this->nack($message);
            }
        } catch (\Throwable $e) {
            dump($e);
//            $this->logger->error(
//                sprintf(
//                    "Could not process message, got %s from %s in %d for message: %s",
//                    get_class($e) . '-' . $e->getMessage(),
//                    (string)$e->getFile(),
//                    (int)$e->getLine(),
//                    (string)$message->getBody()
//                )
//            );
            $this->nack($message);
        }
    }

    public function batchConsume(array $messages): mixed
    {
        if (empty($messages)) {

            return 1;
        }

        dump(sprintf(__METHOD__ . 'Start batch consume %s', count($messages)));
        $this->messageCount = count($messages);

        /**
         * @var  $deliveryTag
         * @var AMQPMessage $message
         */
        foreach ($messages as $deliveryTag => $message) {
            dump($deliveryTag, $message->getBody());
            sleep(2);
        }

        $lastDeliveryTag = array_key_last($messages);
        $this->ack($messages[$lastDeliveryTag], true);

        sleep(5);
        dump('Finnish consume');

        return 1;
    }

    /**
     * @param AMQPMessage $message
     */
    protected function ack(AMQPMessage $message, bool $multiple = false)
    {
        try {
            dump(sprintf("Processed with success message %s", $message->getBody()));

            $this->logger->debug(sprintf("Processed with success message %s", $message->getBody()));
            $message->getChannel()->basic_ack($message->getDeliveryTag(), $multiple);
            $message->{self::HANDLED_PROPERTY} = true;
        } catch (\Throwable $e) {
            dump($e->getMessage(), $e->getTraceAsString());
//            $this->logger->error(
//                sprintf(
//                    "Could not process message, got %s from %s in %d for message: %s",
//                    get_class($e) . '-' . $e->getMessage(),
//                    (string)$e->getFile(),
//                    (int)$e->getLine(),
//                    (string)$message->getBody()
//                )
//            );
        }
    }

    /**
     * @param AMQPMessage $message
     * @param bool $redeliver
     */
    protected function nack(AMQPMessage $message, bool $redeliver = true)
    {
        try {
            //$this->logger->debug(sprintf("Did not processed with success message %s", $message->getBody()));
            $message->delivery_info['channel']->basic_nack($message->delivery_info['delivery_tag'], false, $redeliver);
            $message->{self::HANDLED_PROPERTY} = true;
        } catch (\Throwable $e) {
            dump($e->getMessage());
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