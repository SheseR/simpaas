<?php

namespace Levtechdev\Simpaas\Queue\Manager;

use Levtechdev\Simpaas\Queue\RabbitMq\ConsumerInterface;
use Levtechdev\Simpaas\Queue\RabbitMq\Container;

abstract class AbstractConsumer
{
    /** @var ConsumerInterface|mixed  */
    protected ConsumerInterface $consumer;

    /** @var bool  */
    protected bool $isDebugLevel = false;

    /**
     * @param Container $container
     *
     * @throws \Exception
     */
    public function __construct(protected Container $container)
    {
        $this->consumer = $this->container->getConsumer($this->getAliasName());
    }

    /**
     * @return void
     */
    public function consume(): void
    {
        $this->consumer->startConsuming();
    }

    abstract protected function getAliasName(): string;
}