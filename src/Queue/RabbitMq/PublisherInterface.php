<?php

namespace Levtechdev\Simpaas\Queue\RabbitMq;

use JetBrains\PhpStorm\ArrayShape;

interface PublisherInterface
{
    /**
     * @param array $rawBatchData
     * @param int $priority
     *
     * @return void
     */
    public function publishBatch(
        #[ArrayShape([[
            'body' => 'array',
            'routing_key' => 'string',
            'priority' => 'string'
        ]])] array $rawBatchData,
        int $priority = MessageInterface::PRIORITY_LOW
    ): void;

    /**
     * @param array $rawData
     * @param string $routingKey
     * @return void
     */
    public function publish(
        #[ArrayShape([
            'body' => 'array',
            'priority' => 'int'
    ])] array $rawData, string $routingKey = ''): void;
}