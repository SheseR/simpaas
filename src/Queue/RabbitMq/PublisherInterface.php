<?php

namespace Levtechdev\Simpaas\Queue\RabbitMQ;

interface PublisherInterface
{
    public function publishBatch(array $inputData, int $priority = MessageInterface::PRIORITY_LOW): void;
}