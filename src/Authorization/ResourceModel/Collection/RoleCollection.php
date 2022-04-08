<?php

namespace Levtechdev\Simpaas\Authorization\ResourceModel\Collection;

use Levtechdev\Simpaas\Authorization\Model\Role;
use Levtechdev\Simpaas\Collection\Redis\AbstractRedisCollection;

class RoleCollection extends AbstractRedisCollection
{
    public function __construct(Role $model, $items = [])
    {
        parent::__construct($model, $items);
    }
}
