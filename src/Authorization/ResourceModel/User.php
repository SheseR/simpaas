<?php

namespace Levtechdev\Simpaas\Authorization\ResourceModel;

use Levtechdev\Simpaas\Model\AbstractModel;
use Levtechdev\Simpaas\Authorization\Model\User as UserModel;
use Levtechdev\Simpaas\ResourceModel\Redis\AbstractRedisResourceModel;

class User extends AbstractRedisResourceModel
{

    /**
     * @param AbstractModel|UserModel $object
     *
     * @return $this
     */
    public function beforeSave(AbstractModel|UserModel $object): static
    {
        parent::beforeSave($object);

        // generate token if object new or client_id changed
        if ($object->isObjectNew() || $object->dataHasChangedFor('client_id')) {
            $token = $object->generateToken($object->getId(), $object->getClientId());
            $object->setToken($token);
        }

        return $this;
    }
}