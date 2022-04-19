<?php
namespace Levtechdev\Simpaas\Cams\Queue;

use Levtechdev\Simpaas\Queue\Manager\AbstractPublisher;

class PublisherManager extends AbstractPublisher
{
    function getAliasName(): string
    {
        return 'cams';
    }
}