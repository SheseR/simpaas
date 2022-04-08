<?php

namespace Levtechdev\SimPaas\Authorization\ResourceModel\Collection;

use Levtechdev\SimPaas\Authorization\Model\Rule;
use Levtechdev\SimPaas\Collection\Redis\AbstractRedisCollection;

class RuleCollection extends AbstractRedisCollection
{
    public function __construct(Rule $model, array $items= [])
    {
        parent::__construct($model, $items);
    }
}
