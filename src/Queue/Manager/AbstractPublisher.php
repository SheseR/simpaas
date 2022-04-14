<?php

namespace Levtechdev\Simpaas\Queue\Manager;


use Levtechdev\Simpaas\Exceptions\NotImplementedException;
use Levtechdev\Simpaas\Helper\Logger;
use Levtechdev\Simpaas\Queue\RabbitMq\Container;
use Levtechdev\Simpaas\Queue\RabbitMq\Entity\ExchangeEntity;
use Levtechdev\Simpaas\Queue\RabbitMq\PublisherInterface;

abstract class AbstractPublisher
{
    /** @var string */
    const LOG_CHANNEL = 'publisher-manager';
    /** @var string */
    const LOG_FILE = 'queue.log';

    /** @var string */
    const LOG_PATH = '';

    protected PublisherInterface $publisher;
    protected \Monolog\Logger $logger;
    protected bool $isDebugLevel = false;

    public function __construct(protected Container $container, Logger $loggerHelper)
    {
        $this->isDebugLevel = (bool)env('APP_DEBUG', false);
        $this->publisher = $this->container->getPublisher($this->getAliasName());

        $this->logger = $loggerHelper->getLogger(
            static::LOG_CHANNEL,
            base_path(Logger::LOGS_DIR . static::LOG_PATH . static::LOG_FILE)
        );
    }

    /**
     * @return $this
     *
     * @throws NotImplementedException
     */
    protected function publish(): static
    {
        throw new NotImplementedException();
    }

    /**
     * @param array $inputData
     * @param int $priority
     * @param string $routingKey
     *
     * @return $this
     */
    public function publishBatch(array $inputData, int $priority = 1, string $routingKey = ''): static
    {
        $isRoutingKeyRequired = $this->publisher instanceof ExchangeEntity;
        $massages = [];
        foreach ($inputData as $key => $item) {
            if (!$this->isValidItem($item)) {
                $this->logger->warning(sprintf('Manager: %s: item is not valid', get_called_class()), $item);
                unset($inputData[$key]);

                continue;
            }

            $message = [
                'body' => $item['data'],
                'priority' => $item['priority'] ?? $priority,
            ];

            if ($isRoutingKeyRequired) {
                $message['routing_key'] = $item['routing_key'] ?? $routingKey;
            }

            $massages[] = $message;
        }

        if (empty($messages)) {

            return $this;
        }

        $this->publisher->publishBatch($massages);

        return $this;
    }

    /**
     * @param string $message
     * @param array $context
     *
     * @return $this
     */
    protected function debug(string $message, array $context): static
    {
        if (!$this->isDebugLevel) {

            return $this;
        }

        $this->logger->debug(sprintf('Publisher: %s: %s', $this->getAliasName(), $message), $context);

        return $this;
    }

    /**
     * @param array $data
     *
     * @return bool
     */
    public function isValidItem(array $data): bool
    {
        if (!key_exists('data', $data)) {

            return false;
        }

        return true;
    }

    abstract function getAliasName(): string;
}