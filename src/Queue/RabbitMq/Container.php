<?php

namespace Levtechdev\Simpaas\Queue\RabbitMq;

class Container
{
    /**
     * @var array
     */
    private array $publishers = [];

    /**
     * @var array
     */
    private array $consumers = [];

    /**
     * @param string $publisherName
     * @param PublisherInterface $entity
     * @return Container
     */
    public function addPublisher(string $publisherName, PublisherInterface $entity): Container
    {
        $this->publishers[$publisherName] = $entity;

        return $this;
    }

    /**
     * @param string $publisherName
     *
     * @return PublisherInterface
     */
    public function getPublisher(string $publisherName): PublisherInterface
    {
        return $this->publishers[$publisherName];
    }

    /**
     * @return array
     */
    public function getPublishers(): array
    {
        return $this->publishers;
    }

    /**
     * @param string $publisherName
     * @return bool
     */
    public function hasPublisher(string $publisherName): bool
    {
        return isset($this->publishers[$publisherName]);
    }

    /**
     * @param string $consumerName
     * @param ConsumerInterface $consumer
     * @return Container
     */
    public function addConsumer(string $consumerName, ConsumerInterface $consumer): Container
    {
        $this->consumers[$consumerName] = $consumer;
        return $this;
    }

    /**
     * @param string $consumerName
     * @return mixed
     */
    public function getConsumer(string $consumerName): ConsumerInterface
    {
        return $this->consumers[$consumerName];
    }

    /**
     * @return array
     */
    public function getConsumers(): array
    {
        return $this->consumers;
    }

    /**
     * @param string $consumerName
     * @return bool
     */
    public function hasConsumer(string $consumerName): bool
    {
        return isset($this->consumers[$consumerName]);
    }
}