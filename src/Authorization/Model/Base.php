<?php

namespace Levtechdev\Simpaas\Authorization\Model;

use Levtechdev\Simpaas\Model\Redis\AbstractRedisModel;

class Base extends AbstractRedisModel
{
    const ENTITY           = 'auth_base';
    const ENTITY_ID_PREFIX = 'auth_';

    /**
     * @return $this
     *
     * @throws \Exception
     */
    public function beforeSave(): static
    {
        $this->validateData();

        parent::beforeSave();

        return $this;
    }

    /**
     * Check roles
     * @return void
     * @throws \Exception
     */
    protected function validateData() : void
    {
        // void
    }
}
