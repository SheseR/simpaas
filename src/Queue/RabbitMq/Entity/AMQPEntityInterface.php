<?php

namespace Levtechdev\Simpaas\Queue\RabbitMq\Entity;

interface AMQPEntityInterface
{
    public function create(): void;

    /**
     * Bind the entity
     * @return void
     */
    public function bind(): void;

    /**
     * @return void
     */
    public function delete(): void;

    /**
     * @return string
     */
    public function getAliasName(): string;

    /**
     * Reconnect the entity
     */
    public function reconnect(): void;

    /**
     * @return array
     */
    public function getAttributes(): array;
}