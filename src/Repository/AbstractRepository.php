<?php

namespace Levtechdev\Simpaas\Repository;

use Exception;
use Levtechdev\Simpaas\Collection\AbstractCollection;
use Levtechdev\Simpaas\Model\AbstractModel;

abstract class AbstractRepository
{
    const ENTITIES_CACHE_LIMIT = 500;

    protected array $instances = [];

    /** @var AbstractModel  */
    protected AbstractModel $dataModel;

    public function __construct(protected AbstractCollection $collection)
    {
        $this->dataModel = $collection->getModel();
    }

    /**
     * @return AbstractModel
     */
    public function getDataModel(): AbstractModel
    {
        return $this->dataModel->factoryCreate();
    }

    /**
     * @param array  $items
     * @param string $indexField
     *
     * @return AbstractCollection
     */
    public function getCollection(array $items = [], string $indexField = AbstractModel::ID_FIELD_NAME): AbstractCollection
    {
        return $this->collection->factoryCreate($items, $indexField);
    }

    /**
     * @param array $data
     *
     * @return AbstractModel
     *
     * @throws Exception
     */
    public function save(array $data): AbstractModel
    {
        return $this->getDataModel()
            ->setData($data)
            ->save();
    }

    /**
     * @param string|int $id
     * @param bool $forceReload
     *
     * @return AbstractModel
     */
    public function getById(string|int $id, bool $forceReload = false): AbstractModel
    {
        return $this->loadDataModel($id, AbstractModel::ID_FIELD_NAME, $forceReload);
    }

    /**
     * @param mixed $value
     * @param string $field
     * @param bool $forceReload
     *
     * @return AbstractModel
     */
    public function getByField(mixed $value, string $field, bool $forceReload = false): AbstractModel
    {
        return $this->loadDataModel($value, $field, $forceReload);
    }

    /**
     * @param string $slug
     * @param bool $forceReload
     *
     * @return AbstractModel
     */
    public function getBySlug(string $slug, bool $forceReload = false): AbstractModel
    {
        return $this->loadDataModel($slug, AbstractModel::SLUG_FIELD_NAME, $forceReload);
    }

    /**
     * @param mixed $value
     * @param string $field
     * @param bool $forceReload
     *
     * @return AbstractModel
     */
    public function loadDataModel(mixed $value, string $field, bool $forceReload = false): AbstractModel
    {
        $cacheKey = $this->getCacheKey($value, $field);
        if ($forceReload || !isset($this->instances[static::class][$cacheKey])) {
            $dataModel = $this->getDataModel();

            switch ($field) {
                case AbstractModel::ID_FIELD_NAME:
                    $field = $dataModel->getIdFieldName();
                    break;
                case AbstractModel::SLUG_FIELD_NAME:
                    $field = $dataModel->getSlugFieldName();
                    break;
            }

            $dataModel->load($value, $field);
            $this->cacheEntity($cacheKey, $dataModel);
        }

        return $this->instances[static::class][$cacheKey];
    }

    /**
     * @param $id
     *
     * @return AbstractModel
     * @throws Exception
     */
    public function deleteById($id): AbstractModel
    {
        return $this->getDataModel()
            ->load($id)
            ->delete();
    }

    /**
     * @param       $id
     * @param array $data
     *
     * @return AbstractModel
     * @throws Exception
     */
    public function update($id, array $data): AbstractModel
    {
        return $this->getDataModel()
            ->load($id)
            ->addData($data)
            ->save();
    }

    /**
     * @param string $slug
     * @param array $data
     * @param bool $forceReload
     *
     * @return AbstractModel
     *
     * @throws Exception
     */
    public function updateBySlug(string $slug, array $data, bool $forceReload = false): AbstractModel
    {
        return $this
            ->loadDataModel($slug, AbstractModel::SLUG_FIELD_NAME, $forceReload)
            ->addData($data)
            ->save();
    }

    /**
     * @param array|int|string
     *
     * @return int
     */
    public function exists(array|int|string $ids): int
    {
        return $this->dataModel->exists($ids);
    }

    /**
     * @param array|int|string $ids
     * @param string $field
     *
     * @return int
     */
    public function existsByField(array|int|string $ids, string $field): int
    {
        return $this->dataModel->existsByField($ids, $field);
    }

    /**
     * @param array $searchCriteria
     *
     * @return mixed
     */
    public function getList(array $searchCriteria = []): AbstractCollection
    {
        return $this->getCollection()->setSearchCriteria($searchCriteria)->load();
    }

    /**
     * Get cache key by specified arguments
     *
     * @param array ...$parts
     *
     * @return string
     */
    protected function getCacheKey(...$parts): string
    {
        $serializeData = [];
        foreach ($parts as $key => $value) {
            if (is_object($value)) {
                $serializeData[$key] = $value->getId();
            } else {
                $serializeData[$key] = $value;
            }
        }
        $serializeData = serialize($serializeData);

        return sha1($serializeData);
    }

    /**
     * Add entity to internal cache and truncate cache if it has more than cacheLimit elements
     *
     * @param string                        $key
     * @param AbstractModel $entity
     */
    protected function cacheEntity(string $key, AbstractModel $entity): void
    {
        $this->instances[static::class][$key] = $entity;

        if (self::ENTITIES_CACHE_LIMIT && count($this->instances[static::class]) > self::ENTITIES_CACHE_LIMIT) {
            $offset = round(self::ENTITIES_CACHE_LIMIT / -2);
            $this->instances[static::class] = array_slice($this->instances[static::class], $offset, null, true);
            $this->instances[static::class] = array_slice($this->instances[static::class], $offset, null, true);
        }
    }

    /**
     * @param array $data
     *
     * @return AbstractCollection
     */
    abstract public function massSave(array $data): AbstractCollection;
    abstract public function massDelete(AbstractCollection $collection): AbstractCollection;
}
