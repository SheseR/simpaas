<?php

namespace Levtechdev\Simpaas\Cams\Queue;

use Levtechdev\Simpaas\Cams\Service\NotifierService;
use Levtechdev\Simpaas\Queue\RabbitMq\Processor\AbstractMessageProcessor;
use PhpAmqpLib\Message\AMQPMessage;
use Throwable;

class MessageProcessor extends AbstractMessageProcessor
{
    public function __construct(protected NotifierService $service)
    {
    }

    protected function processMessage(AMQPMessage $message): bool
    {
        // TODO: Implement processMessage() method.
    }

    /**
     * @param array $batchTransferMessages
     *
     * @return array
     * @throws Throwable
     */
    protected function processTransferMessages(array $batchTransferMessages): array
    {
        return $this->service->setLogger($this->logger)->process($batchTransferMessages);
    }
}