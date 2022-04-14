<?php
namespace Levtechdev\Simpaas\Queue\RabbitMq\Processor;

use PhpAmqpLib\Message\AMQPMessage;

interface MessageProcessorInterface
{
    public function consume(AMQPMessage $message);

    /**
     * @return int
     */
    public function getProcessedMessages(): int;
}