<?php

namespace Levtechdev\Simpaas\Cams\Queue;

use Levtechdev\Simpaas\Queue\Manager\AbstractConsumer;

class ConsumerManager extends AbstractConsumer
{
    protected function getAliasName(): string
    {
        return 'cams-consumer';
    }
}