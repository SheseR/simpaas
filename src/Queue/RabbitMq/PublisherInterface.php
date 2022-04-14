<?php

namespace Levtechdev\Simpaas\Queue\RabbitMq;

interface PublisherInterface
{
    public function publishBatch(array $inputData, int $priority = MessageInterface::PRIORITY_LOW): void;

    public function publish(string $message, string $routingKey = ''): void;

}