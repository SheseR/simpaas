<?php
namespace Levtechdev\Simpaas\ResourceModel\Redis;

use Illuminate\Contracts\Redis\Connection;
use Levtechdev\Simpaas\Collection\Redis\AbstractRedisCollection;
use Levtechdev\Simpaas\Database\DbAdapterInterface;
use Levtechdev\Simpaas\Database\Redis\RedisAdapter;
use Levtechdev\Simpaas\Exceptions\EntityNotFoundException;
use Levtechdev\Simpaas\Exceptions\MethodNotAllowedException;
use Levtechdev\Simpaas\Model\AbstractModel;
use Levtechdev\Simpaas\Model\Redis\AbstractRedisModel;
use Levtechdev\Simpaas\ResourceModel\AbstractResourceModel;

class AbstractRedisResourceModel extends AbstractResourceModel
{
    const ID_KEY_DELIMITER = '::';
    const DEFAULT_COUNT = 20;

    /**
     * Redis record TTL when adding/updating
     *
     * Null means - doesn't expire
     *
     * @var int|null
     */
    protected int|null $ttl = null;

    public function __construct(RedisAdapter $dbAdapter)
    {
        parent::__construct($dbAdapter);

        $this->connection = $dbAdapter->connection(static::CONNECTION_NAME);
    }

    /**
     * @return DbAdapterInterface|RedisAdapter
     */
    public function getAdapter(): DbAdapterInterface|RedisAdapter
    {
        return $this->adapter;
    }

    /**
     * @param int|null $ttl
     *
     * @return $this
     */
    public function setTtl(?int $ttl): static
    {
        $this->ttl = $ttl;

        return $this;
    }

    /**
     * @param string $key
     * @param mixed $data
     *
     * @return $this
     */
    public function saveData(string $key, mixed $data): static
    {
        $connection = $this->getConnection();

        $data = json_encode($data);
        if ($this->ttl) {
            $connection->set($key, $data, 'EX', $this->ttl);
            $this->setTtl(null);
        } else {
            $connection->set($key, $data);
        }

        return $this;
    }

    /**
     * @param string     $entityType
     * @param string|int $id
     *
     * @return string
     */
    public function getIdKey(string $entityType, string|int $id): string
    {
        return $entityType . self::ID_KEY_DELIMITER . $id;
    }

    /**
     * @param AbstractModel $object
     * @param array|string|int $ids
     *
     * @return int
     */
    public function exists(AbstractModel $object, array|string|int $ids): int
    {
        /** @var \Illuminate\Redis\Connections\Connection $connection */
        $connection = $this->getConnection();
        $idKeys = [];
        if (is_array($ids)) {
            foreach ($ids as $id)
                $idKeys[] = $this->getIdKey($object::ENTITY, $id);
        } else {
            $idKeys = [$this->getIdKey($object::ENTITY, $ids)];
        }

        return (int)$connection->exists(...$idKeys);
    }

    /**
     * @param AbstractModel $object
     *
     * @param array|string|int|float|bool|null $ids
     * @param string $fieldName
     * @return int
     * @throws MethodNotAllowedException
     */
    public function existsByField(AbstractModel $object, array|string|int|float|bool|null $ids, string $fieldName): int
    {
        throw new MethodNotAllowedException();
    }

    /**
     * @param AbstractRedisModel $object
     * @param $id
     * @param $ttl
     *
     * @return int
     */
    public function expire(AbstractRedisModel $object, $id, $ttl): int
    {
        /** @var \Illuminate\Redis\Connections\Connection $connection */
        $connection = $this->getConnection();

        return (int)$connection->expire($this->getIdKey($object::ENTITY, $id), $ttl);
    }

    /**
     * @param     $cursor
     * @param     $pattern
     * @param int $count
     *
     * @return array
     */
    public function scan(&$cursor, $pattern, int $count=self::DEFAULT_COUNT): array
    {
        /** @var \Illuminate\Redis\Connections\Connection $connection */
        $connection = $this->getConnection();

        if ($cursor===null) {
            $cursor = '0';
        }

        $result = $connection->command('SCAN', [&$cursor, $pattern, $count]);
        if (empty($result)) {
            return [];
        }

        return array_map(function($item){
            return explode(self::ID_KEY_DELIMITER, $item)[1] ?? $item[0];
        }, $result);
    }
    /**
     * @param AbstractRedisCollection $collection
     *
     * @return $this
     */
    public function massDelete(AbstractRedisCollection $collection): static
    {
        $idKeys = $collection->getIdKeys();

        if (!empty($idKeys)) {
            $this->getConnection()->del($idKeys);
        }

        return $this;
    }

    /**
     * @param AbstractModel|AbstractRedisModel $object
     * @param string|int|float|bool|null $id
     * @param string $field
     * @param array $excludedFields
     *
     * @return void
     *
     * @throws EntityNotFoundException
     */
    protected function objectLoad(
        AbstractModel|AbstractRedisModel $object,
        string|int|float|bool|null $id,
        string $field = AbstractModel::ID_FIELD_NAME,
        array $excludedFields = []
    ): void {

        $connection = $this->getConnection();
        $idKey = $this->getIdKey($object::ENTITY, $id);
        $data = $connection->get($idKey);
        if (!$data) {
            throw new EntityNotFoundException(
                sprintf('Entity "%s" with ID "%s" was not found', $object::ENTITY, $id)
            );
        }

        $object->setData(json_decode($data, true));
    }

    /**
     * @param AbstractModel|AbstractRedisModel $object
     *
     * @return void
     */
    protected function objectSave(AbstractModel|AbstractRedisModel $object): void
    {
        if ($object->isObjectNew()) {
            $object->isObjectNew(true);
            $object->setId($object->generateUniqueId());
        }

        $this->beforeSave($object);

        $idKey = $this->getIdKey($object::ENTITY, $object->getId());
        $this->saveData($idKey, $object->getData());

        $object->setHasDataChanges(false);
    }

    /**
     * @param AbstractModel|AbstractRedisModel $object
     *
     * @return void
     */
    protected function objectDelete(AbstractModel|AbstractRedisModel $object): void
    {
        $connection = $this->getConnection();

        $idKey = $this->getIdKey($object::ENTITY, $object->getId());
        $connection->del([$idKey]);
    }
}