<?php

namespace SimPass\Authorization\ResourceModel\Collection;

use Levtechdev\SimPaas\Authorization\Model\User;
use Levtechdev\SimPaas\Collection\Redis\AbstractRedisCollection;
use Levtechdev\SimPaas\Model\AbstractModel;

class UserCollection extends AbstractRedisCollection
{
    public function __construct(User $model, array $items =[])
    {
        parent::__construct($model, $items);
    }

    /**
     * @param string $clientId
     *
     * @return AbstractModel|User
     */
    public function getItemByClientId(string $clientId): AbstractModel|User
    {
        /** @var User $item */
        foreach ($this->items as $item) {
            if ($item->getData('client_id') === $clientId) {

                return $item;
            }
        }

        return $this->getModel()->factoryCreate();
    }
}
