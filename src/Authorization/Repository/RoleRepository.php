<?php

namespace Levtechdev\SimPaas\Authorization\Repository;

use Levtechdev\SimPaas\Authorization\ResourceModel\Collection\RoleCollection;
use Levtechdev\SimPaas\Repository\Redis\AbstractRedisRepository;

class RoleRepository extends AbstractRedisRepository
{
    public function __construct(RoleCollection $collection)
    {
        parent::__construct($collection);
    }
}