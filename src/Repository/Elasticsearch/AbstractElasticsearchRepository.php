<?php
namespace Levtechdev\Simpaas\Repository\Elasticsearch;

use Levtechdev\Simpaas\Collection\AbstractCollection;
use Levtechdev\Simpaas\Collection\Elasticsearch\AbstractElasticsearchCollection;
use Levtechdev\Simpaas\Database\SearchCriteria;
use Levtechdev\Simpaas\Exceptions\EmptyCollectionException;
use Levtechdev\Simpaas\Exceptions\NoDataChangesException;
use Levtechdev\Simpaas\Exceptions\NotImplementedException;
use Levtechdev\Simpaas\Model\AbstractModel;
use Levtechdev\Simpaas\Model\Elasticsearch\AbstractElasticsearchModel;
use Levtechdev\Simpaas\Repository\AbstractRepository;
use Levtechdev\Simpaas\Validation\ValidationErrorsTrait;

class AbstractElasticsearchRepository extends AbstractRepository
{
    // @todo not implemented yet in the class
    use ValidationErrorsTrait;

    public function __construct(AbstractElasticsearchCollection $collection)
    {
       parent::__construct($collection);
    }

    /**
     * @param array $data
     *
     * @return AbstractElasticsearchCollection
     */
    public function massSave(array $data): AbstractElasticsearchCollection
    {
        $this->validateBulkData($data);

        return $this->executeMassSave($data);
    }

    /**
     * @param AbstractCollection $collection
     *
     * @return AbstractCollection
     *
     * @throws NotImplementedException
     */
    public function massDelete(AbstractCollection $collection): AbstractCollection
    {
        throw new NotImplementedException();
    }

    /**
     * @param array $data
     *
     * @return AbstractElasticsearchModel
     *
     * @throws \Exception
     */
    public function save(array $data): AbstractElasticsearchModel
    {
        $dataModel = $this->getDataModel();
        $dataModel->setData($data);
        $this->validateModel($dataModel);
        $dataModel->save();

        event($dataModel::ENTITY . '.model.add.after', ['params' => ['model' => $dataModel]]);

        return $dataModel;
    }

    /**
     * @param        $id
     * @param array  $data
     * @param string $comment
     *
     * @return AbstractElasticsearchModel
     *
     * @throws \Exception
     */
    public function update($id, array $data, string $comment = ''): AbstractElasticsearchModel
    {
        $dataModel = $this->getById($id, true);
        $dataModel->addData($data);

        $dataChanges = $dataModel->getDataChanges();

        $this->validateModel($dataModel);

        $dataModel->setComment($comment);
        $dataModel->save();

        event($dataModel::ENTITY . '.model.update.after', [
            'params' => [
                'model'       => $dataModel,
                'dataChanges' => $dataChanges
            ]
        ]);

        return $dataModel;
    }

    /**
     * @param string|int $id
     * @param bool       $forceReload
     *
     * @return AbstractElasticsearchModel
     */
    public function getById(string|int $id, bool $forceReload = false): AbstractElasticsearchModel
    {
        $cacheKey = $this->getCacheKey($id);
        if (!isset($this->instances[static::class][$cacheKey]) || $forceReload) {

            $dataModel = $this->getDataModel();
            $dataModel->load($id);
            $this->cacheEntity($cacheKey, $dataModel);
        }

        return $this->instances[static::class][$cacheKey];
    }

    /**
     * @param string $value
     * @param string $field
     * @param bool   $forceReload
     *
     * @return array
     */
    public function getByField(mixed $value, string $field, bool $forceReload = false): AbstractElasticsearchModel
    {
        $cacheKey = $this->getCacheKey($value, $field);
        if (!isset($this->instances[static::class][$cacheKey]) || $forceReload) {

            $dataModel = $this->getDataModel();
            $dataModel->load($value, $field);
            $this->cacheEntity($cacheKey, $dataModel);
        }

        return $this->instances[static::class][$cacheKey];
    }

    /**
     * @param $id
     *
     * @return AbstractElasticsearchModel
     *
     * @throws \Exception
     */
    public function deleteById($id): AbstractElasticsearchModel
    {
        /** @var AbstractElasticsearchModel $dataModel */
        $dataModel = $this->getDataModel()->load($id);
        if ($dataModel->getId() && $dataModel->delete()) {

            event($dataModel::ENTITY . '.model.delete.after', ['params' => ['model' => $dataModel]]);
        }

        return $dataModel;
    }

    /**
     * @param array $searchCriteria
     *
     * @return AbstractElasticsearchCollection
     */
    public function getList(array $searchCriteria = []): AbstractElasticsearchCollection
    {
        return $this->getCollection()->setSearchCriteria($searchCriteria)->load();
    }

    /**
     * @param array $items
     * @param string $indexField
     *
     * @return AbstractCollection|AbstractElasticsearchCollection
     */
    public function getCollection(array $items = [], string $indexField = AbstractModel::ID_FIELD_NAME): AbstractCollection|AbstractElasticsearchCollection
    {
        return $this->collection->factoryCreate($items, $indexField);
    }

    /**
     * @param array $data
     *
     * @return AbstractElasticsearchCollection
     */
    public function executeMassSave(array $data): AbstractElasticsearchCollection
    {
        return $this->collection->bulkCreate($data['data'], false, $data['comment'] ?? '');
    }

    /**
     * @param array $data
     * @param string $comment
     *
     * @return AbstractElasticsearchCollection
     *
     * @throws EmptyCollectionException
     * @throws NoDataChangesException
     */
    public function massUpdate(array $data, string $comment = ''): AbstractElasticsearchCollection
    {
        $this->validateBulkData($data);

        return $this->executeMassUpdate($data, $comment);
    }

    /**
     * @param array $data
     * @param string $comment
     *
     * @return AbstractElasticsearchCollection
     *
     * @throws EmptyCollectionException
     * @throws NoDataChangesException
     */
    public function executeMassUpdate(array $data, string $comment = ''): AbstractElasticsearchCollection
    {
        return $this->getCollection()->bulkUpdate($data['data'], false, $comment);
    }

    /**
     * @param array $data
     *
     * @return $this
     */
    protected function validateBulkData(array $data): self
    {
        if (empty($data['data'])) {
            throw new \InvalidArgumentException('Data payload is missing');
        }

        return $this;
    }

    /**
     * Disallow system fields from input data
     *
     * @todo this MUST be done on JSON schema level, but additionalProperties for some reason can't be used now
     *
     * @param array $data
     *
     * @return array
     */
    public function prepareRawInputData(array $data): array
    {
        $dataModel = $this->collection->getModel();
        $notAllowedFields = $dataModel->getResource()->getSystemFields();
        $resultedData = $data;

        /**
         * Multi-items data mode
         */
        if (!key_exists('data', $resultedData)) {
            foreach ($notAllowedFields as $field) {
                if (key_exists($field, $resultedData)) {
                    unset($resultedData[$field]);
                }
            }

            return $resultedData;
        }

        foreach ($resultedData['data'] as &$item) {
            foreach ($notAllowedFields as $field) {
                if (key_exists($field, $item)) {
                    unset($item[$field]);
                }
            }
        }

        return $resultedData;
    }

    /**
     * @param array $data
     *
     * @return mixed
     *
     * @throws EmptyCollectionException|\Exception
     */
    public function massUpdateByFilter(array $data): mixed
    {
        $this->validateBulkData($data);
        $filter = $data['data'];

        $collection = $this->getCollection()->setSearchCriteria($filter);
        $size = $collection->getSize();
        if (!$size) {
            throw new EmptyCollectionException();
        }

        return $this->executeMassUpdateByFilter($data);
    }

    /**
     * @param array $data
     *
     * @return mixed
     */
    public function executeMassUpdateByFilter(array $data): mixed
    {
        $collection = $this->getCollection()->setSearchCriteria($data['data']);
        return $this->collection->getAdapter()->enforceWriteConnection(
            function () use ($data, $collection) {

                return $collection->updateRecordsByFilter(
                    $collection->getPreparedQuery(), $data['data']['update']
                );
            }
        );
    }

    /**
     * @param string|int|array $ids
     *
     * @return mixed
     */
    public function exists(string|int|array $ids): int
    {
        return $this->dataModel->exists($ids);
    }

    /**
     * @param \Closure $callback
     * @param array    $args
     *
     * @return mixed
     */
    public function executeSynchronously(\Closure $callback, array $args = []): mixed
    {
        return $this->getDataModel()->getResource()->getAdapter()->enforceWriteConnection($callback, $args);
    }

    /**
     * @param AbstractElasticsearchModel $dataModel
     *
     * @return bool
     */
    public function validateModel(AbstractElasticsearchModel $dataModel): bool
    {
        return true;
    }

    /**
     * Load collection by specified IDs
     *
     * @param array $ids
     * @param array $fields
     *
     * @return AbstractElasticsearchCollection
     */
    public function getCollectionByIds(array $ids, array $fields = []): AbstractElasticsearchCollection
    {
        return $this->getCollection()
            ->clear()
            ->addIdsFilter($ids)
            ->addFieldToSelect($fields)
            ->limit(SearchCriteria::MAX_PAGE_SIZE)
            ->load();
    }
}