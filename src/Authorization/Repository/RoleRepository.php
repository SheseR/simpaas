<?php

namespace Levtechdev\Simpaas\Authorization\Repository;

use Levtechdev\Simpaas\Authorization\ResourceModel\Collection\RoleCollection;
use Levtechdev\Simpaas\Repository\Redis\AbstractRedisRepository;

class RoleRepository extends AbstractRedisRepository
{
    public function __construct(RoleCollection $collection)
    {
        parent::__construct($collection);
    }
}