<?php

namespace Levtechdev\Simpaas\Queue\RabbitMq;

interface MessageInterface
{
    const PRIORITY_LOW    = 1;
    const PRIORITY_NORMAL = 2;
    const PRIORITY_HIGH   = 3;
}