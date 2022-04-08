<?php

namespace Levtechdev\SimPaas\Helper;

use Psr\Log\LoggerInterface;

abstract class ProxyLogger implements LoggerInterface
{
    private bool $isDebugLevel;
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->isDebugLevel = env('CHANNEL_ADVISOR_DATA_DEBUG', false);
        $this->logger = $logger;
    }

    public function emergency(string|\Stringable $message, array $context = array()): void
    {
        $this->logger->emergency($message, $context);
    }

    public function alert(string|\Stringable $message, array $context = array()): void
    {
        $this->logger->alert($message, $context);
    }

    public function critical(string|\Stringable $message, array $context = array()): void
    {
        $this->logger->critical($message, $context);
    }

    public function error(string|\Stringable $message, array $context = array()): void
    {
        $this->logger->error($message, $context);
    }

    public function warning(string|\Stringable $message, array $context = array()): void
    {
        $this->logger->warning($message, $context);
    }

    public function notice(string|\Stringable $message, array $context = array()): void
    {
        $this->logger->notice($message, $context);
    }

    public function info(string|\Stringable $message, array $context = array()): void
    {
        $this->logger->info($message, $context);
    }

    public function debug(string|\Stringable $message, array $context = array()): void
    {
        if ($this->isDebugLevel) {
            $this->logger->debug($message, $context);
        }
    }

    public function log($level, string|\Stringable $message, array $context = array()): void
    {
        $this->logger->log($level, $message, $context);
    }
}
