<?php

namespace Levtechdev\Simpaas\Authorization\Repository;

use Levtechdev\Simpaas\Authorization\Model\User;
use Levtechdev\Simpaas\Repository\Redis\AbstractRedisRepository;
use Levtechdev\Simpaas\Authorization\ResourceModel\Collection\UserCollection;

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