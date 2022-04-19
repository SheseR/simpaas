<?php
include_once __DIR__ . DIRECTORY_SEPARATOR . 'AbstractQueueProcessor.php';

use Levtechdev\Simpaas\Queue\RabbitMq\Container;

class CamsQueueProcessor extends AbstractQueueProcessor
{
    protected function getConsumerAliasName(): string
    {
        return 'cams-consumer';
    }
}

/** @var Container $container */
$container = app()->get(Container::class);

$inst = new CamsQueueProcessor($container);
$inst->handle();
