<?php

namespace Levtechdev\SimPaas\Authorization\Repository;

use Levtechdev\SimPaas\Authorization\ResourceModel\Collection\RuleCollection;
use Levtechdev\SimPaas\Repository\Redis\AbstractRedisRepository;

class RuleRepository extends AbstractRedisRepository
{
    public function __construct(RuleCollection $collection)
    {
        parent::__construct($collection);
    }
}