<?php
namespace Levtechdev\Simpaas\Repository\Redis;

use Levtechdev\Simpaas\Collection\AbstractCollection;
use Levtechdev\Simpaas\Collection\Redis\AbstractRedisCollection;
use Levtechdev\Simpaas\Exceptions\NotImplementedException;
use Levtechdev\Simpaas\Repository\AbstractRepository;

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