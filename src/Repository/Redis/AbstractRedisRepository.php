<?php
namespace Levtechdev\SimPaas\Repository\Redis;

use Levtechdev\SimPaas\Collection\AbstractCollection;
use Levtechdev\SimPaas\Collection\Redis\AbstractRedisCollection;
use Levtechdev\SimPaas\Exceptions\NotImplementedException;
use Levtechdev\SimPaas\Repository\AbstractRepository;

class AbstractRedisRepository extends AbstractRepository
{
    public function __construct(AbstractRedisCollection $collection)
    {
        parent::__construct($collection);
    }

    /**
     * @param AbstractCollection|AbstractRedisCollection $collection
     *
     * @return AbstractRedisCollection
     */
    public function massDelete(AbstractCollection|AbstractRedisCollection $collection): AbstractRedisCollection
    {
        $this->getDataModel()->getResource()->massDelete($collection);

        return $collection;
    }

    /**
     * @param array $data
     *
     * @return AbstractCollection
     * @throws NotImplementedException
     */
    public function massSave(array $data): AbstractCollection
    {
        // TODO: Implement massSave() method.
        throw new NotImplementedException(__METHOD__);
    }
}