<?php

namespace Levtechdev\Simpaas\Queue\Manager;

use Levtechdev\Simpaas\Helper\Logger;
use Levtechdev\Simpaas\Queue\RabbitMq\ConsumerInterface;
use Levtechdev\Simpaas\Queue\RabbitMq\Container;

abstract class AbstractConsumer
{
    /** @var string */
    const LOG_CHANNEL = 'publisher-manager';
    /** @var string */
    const LOG_FILE = 'queue.log';
    /** @var string */
    const LOG_PATH = '';

    protected ConsumerInterface $consumer;

    protected bool $isDebugLevel = false;
    protected \Monolog\Logger $logger;

    public function __construct(protected Container $container, Logger $loggerHelper)
    {
        $this->isDebugLevel = (bool)env('APP_DEBUG', false);
        $this->consumer = $this->container->getConsumer($this->getAliasName());

        $this->logger = $loggerHelper->getLogger(
            static::LOG_CHANNEL,
            base_path(Logger::LOGS_DIR . static::LOG_PATH . static::LOG_FILE)
        );
    }

    public function consume(): void
    {
        $this->consumer->startConsuming(10000, 10000, 11111111111);
    }

    abstract protected function getAliasName(): string;
}