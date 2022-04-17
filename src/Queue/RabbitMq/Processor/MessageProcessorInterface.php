<?php
namespace Levtechdev\Simpaas\Queue\RabbitMq\Processor;

use PhpAmqpLib\Message\AMQPMessage;

interface MessageProcessorInterface
{
    public function consume(AMQPMessage $message);

    /**
     * @param AMQPMessage[] $messages
     *
     * @return mixed
     */
    public function batchConsume(array $messages): mixed;

    /**
     * @return int
     */
    public function getProcessedMessages(): int;
}