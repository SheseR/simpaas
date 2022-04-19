<?php
namespace Levtechdev\Simpaas\Service\Logger;

use Psr\Log\LoggerInterface;

trait DebugLogTrait
{
    protected bool $isDebugLevel = true;

    /**
     * @return $this
     */
    public function setIsDebugLevel(): self
    {
        $this->isDebugLevel = (bool)env('APP_DEBUG', true);

        return $this;
    }

    /**
     * @param string $message
     * @param array $context
     *
     * @return $this
     */
    public function debug(string $message, array $context = []): self
    {
        if (!($this->logger instanceof LoggerInterface && $this->isDebugLevel)) {

            return $this;
        }

        $this->logger->debug($message, $context);

        return $this;
    }
}