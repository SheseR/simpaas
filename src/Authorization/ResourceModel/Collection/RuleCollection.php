<?php

namespace Levtechdev\Simpaas\Authorization\ResourceModel\Collection;

use Levtechdev\Simpaas\Authorization\Model\Rule;
use Levtechdev\Simpaas\Collection\Redis\AbstractRedisCollection;

class RuleCollection extends AbstractRedisCollection
{
    public function __construct(Rule $model, array $items= [])
    {
        parent::__construct($model, $items);
    }
}
