<?php

namespace Levtechdev\Simpaas\Queue\RabbitMq;

interface ConsumerInterface
{
    /**
     * Consume messages
     *
     * @param int $messages The number of message
     * @param int $seconds  The amount of time a consumer should listen for messages
     * @param int $maxMemory    The amount of memory when a consumer should stop consuming
     * @return mixed
     */
    public function startConsuming(int $messages = 0, int $seconds = 0, int $maxMemory = 0);

    /**
     * Stop the consumer
     */
    public function stopConsuming();

    /**
     * @return array|null
     */
    public function getQueueInfo(): array|null;

    /**
     * @return string
     */
    public function getQueueAliasName(): string;
}