<?php

namespace Levtechdev\SimPaas\Authorization\ResourceModel\Collection;

use Levtechdev\SimPaas\Authorization\Model\Role;
use Levtechdev\SimPaas\Collection\Redis\AbstractRedisCollection;

class RoleCollection extends AbstractRedisCollection
{
    public function __construct(Role $model, $items = [])
    {
        parent::__construct($model, $items);
    }
}
