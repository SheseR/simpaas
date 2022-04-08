<?php

namespace Levtechdev\Simpaas\Authorization\Repository;

use Levtechdev\Simpaas\Authorization\ResourceModel\Collection\RuleCollection;
use Levtechdev\Simpaas\Repository\Redis\AbstractRedisRepository;

class RuleRepository extends AbstractRedisRepository
{
    public function __construct(RuleCollection $collection)
    {
        parent::__construct($collection);
    }
}