<?php

namespace Levtechdev\SimPaas\Authorization\Repository;

use Levtechdev\SimPaas\Authorization\Model\User;
use Levtechdev\SimPaas\Repository\Redis\AbstractRedisRepository;
use SimPass\Authorization\ResourceModel\Collection\UserCollection;

/**
 * @method User getDataModel()
 */
class UserRepository extends AbstractRedisRepository
{
    public function __construct(UserCollection $collection)
    {
        parent::__construct($collection);
    }

    /**
     * @param string|null $userId
     * @param string $clientId
     *
     * @return string
     */
    public function generateToken(?string $userId, string $clientId): string
    {
        return $this->getDataModel()->generateToken($userId, $clientId);
    }
}