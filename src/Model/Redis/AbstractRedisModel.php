<?php
namespace Levtechdev\SimPaas\Model\Redis;

use Levtechdev\SimPaas\Model\AbstractModel;

abstract class AbstractRedisModel extends AbstractModel
{
    /**
     * @param array $data
     * @return $this
     */
    protected function createNewObject(array $data = []): static
    {
        return new static($this->getResource(), $this->hashHelper, $data);
    }
}