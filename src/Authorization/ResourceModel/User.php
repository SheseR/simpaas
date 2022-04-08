<?php

namespace Levtechdev\SimPaas\Authorization\ResourceModel;

use Levtechdev\SimPaas\Model\AbstractModel;
use Levtechdev\SimPaas\Authorization\Model\User as UserModel;
use Levtechdev\SimPaas\ResourceModel\Redis\AbstractRedisResourceModel;

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