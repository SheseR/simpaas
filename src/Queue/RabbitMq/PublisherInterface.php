<?php

namespace Levtechdev\Simpaas\Queue\RabbitMq;

interface PublisherInterface
{
    /**
     * @param array $rawBatchData
     * @param int $priority
     *
     * @return void
     */
    public function publishBatch(array $rawBatchData, int $priority = MessageInterface::PRIORITY_LOW): void;

    /**
     * @param array $rawMessage
     * @param string $routingKey
     *
     * @return void
     */
    public function publish(array $rawMessage, string $routingKey = ''): void;
}