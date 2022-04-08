<?php declare(strict_types=1);

namespace Levtechdev\SimPaas\Collection\Mysql;

use Illuminate\Database\Query\Builder;
use Illuminate\Contracts\Container\BindingResolutionException;
use Levtechdev\SimPaas\Collection\AbstractCollection;
use Levtechdev\SimPaas\Database\DbAdapterInterface;
use Levtechdev\SimPaas\Database\Mysql\MysqlAdapter;
use Levtechdev\SimPaas\Database\Mysql\QueryBuilder;
use Levtechdev\SimPaas\Database\SearchCriteria;
use Levtechdev\SimPaas\Exceptions\BadRequestException;
use Levtechdev\SimPaas\Exceptions\EmptyCollectionException;
use Levtechdev\SimPaas\Model\AbstractModel;
use Levtechdev\SimPaas\Model\DataObject;
use Levtechdev\SimPaas\Model\Mysql\AbstractMysqlModel;
use Levtechdev\SimPaas\ResourceModel\Mysql\AbstractMysqlResourceModel;
use Levtechdev\SimPaas\Exceptions\MysqlUpsertException;
use Levtechdev\SimPaas\Exceptions\MysqlCallbackException;

abstract class AbstractMysqlCollection extends AbstractCollection
{
    protected array  $rawSearchResults     = [];
    protected array  $expressionsToSelect  = [];
    protected array  $fieldsToExclude      = [];
    protected int    $offset               = -1;
    protected array  $joins                = [];
    protected array  $groupsBy             = [];
    protected array  $where                = [];
    protected array  $whereIn              = [];
    protected array  $whereNull            = [];
    protected string $whereNullCondition   = SearchCriteria::CONDITION_AND;
    protected array  $orWhere              = [];
    protected array  $aggregation          = [];
    protected array  $aggregationsToSelect = [];
    protected int    $scrollLastId         = -1;
    protected bool   $useTotalCount        = false;
    protected array  $distinct             = [];
    protected array  $fromRaw              = [];

    /**
     * MysqlCollection constructor.
     *
     * @param AbstractModel $model
     * @param array         $items
     * @param string        $indexField
     */
    public function __construct(
        AbstractModel $model,
        array $items = [],
        string $indexField = AbstractModel::ID_FIELD_NAME
    ) {
        parent::__construct($model, $items, $indexField);
    }

    /**
     * @return DbAdapterInterface|MysqlAdapter
     */
    public function getAdapter(): DbAdapterInterface|MysqlAdapter
    {
        return $this->getModel()->getResource()->getAdapter();
    }

    /**
     * @return $this
     */
    public function clear(): self
    {
        parent::clear();

        $this->limit = -1;
        $this->expressionsToSelect = [];
        $this->aggregation = [];
        $this->aggregationsToSelect = [];
        $this->rawSearchResults = [];
        $this->fieldsToExclude = [];
        $this->offset = -1;
        $this->joins = [];
        $this->groupsBy = [];

        $this->where = [];
        $this->whereIn = [];
        $this->whereNull = [];
        $this->whereNullCondition = SearchCriteria::CONDITION_AND;
        $this->orWhere = [];
        $this->scrollLastId = -1;
        $this->useTotalCount = false;

        return $this;
    }

    /**
     * @return AbstractModel|AbstractMysqlModel
     */
    public function getModel(): AbstractModel|AbstractMysqlModel
    {
        return $this->model;
    }

    /**
     * @param string|null $slug
     *
     * @return $this
     */
    public function load(string $slug = null): static
    {
        if ($this->isLoaded()) {

            return $this;
        }

        if (!empty($slug)) {
            $this->setIndexField($slug);
        }

        $queryBuilder = $this->getPreparedQueryBuilder();

        $this->data = $this->processRows($this->loadResourceData($queryBuilder));
        foreach ($this->data as $item) {
            $object = $this->getModel()->factoryCreate($item);
            $object->afterLoad();
            $object->setHasDataChanges(false);
            $this->addItem($object);
        }

        $this->prepareCountData($queryBuilder);
        $this->prepareScrollLastId();

        $this->setAggregations($this->processAggregations($this->loadAggregationData($queryBuilder)));

        $this->setIsLoaded(true);

        return $this;
    }

    /**
     * @param Builder|null $builder
     *
     * @return mixed
     */
    protected function loadResourceData(?Builder $builder): mixed
    {
        if ($builder === null) {

            return [];
        }

        return $this->getAdapter()->select($builder);
    }

    /**
     * @param array $rows
     *
     * @return array
     */
    protected function processRows(array $rows): array
    {
        /** @var AbstractMysqlResourceModel $resourceModel */
        $resourceModel = $this->getModel()->getResource();
        foreach ($rows as $key => &$row) {
            $rows[$key] = $resourceModel->processRecord($row);
        }

        return $rows;
    }

    /**
     * Get all data for collection w/o creating collection object items
     *
     * @return array
     */
    public function getData(): array
    {
        if ($this->data === null) {
            $this->data = $this->processRows($this->loadResourceData($this->getPreparedQueryBuilder()));
        }

        return $this->data;
    }

    /**
     * @return $this
     */
    protected function prepareScrollLastId(): self
    {
        if ($this->getScrollLastId() === -1) {

            return $this;
        }

        if ($this->isEmpty()) {

            return $this->setScrollLastId(-1);
        }

        return $this->setScrollLastId($this->getLastItem()->getId() ?? -1);
    }

    /**
     * @return $this
     */
    public function useTotalCount(): self
    {
        $this->useTotalCount = true;

        return $this;
    }

    /**
     * @param Builder $builder
     *
     * @return $this
     */
    protected function prepareCountData(Builder $builder): self
    {
        if (!$this->useTotalCount) {

            return $this;
        }

        $this->setTotalItemsCount($this->getAdapter()->countRecords($builder));
        if (!empty($this->getLimit())) {
            $this->setTotalPagesCount($this->getTotalItemsCount(), $this->getLimit());
        }

        return $this;
    }

    /**
     * @param Builder $builder
     *
     * @return array
     */
    protected function loadAggregationData(Builder $builder): array
    {
        $aggregations = [];
        if (empty($this->aggregationsToSelect)) {

            return $aggregations;
        }

        // todo to need refactoring/implementing field=>function
        foreach ($this->aggregationsToSelect as $aggregationField) {
            $aggregations[$aggregationField]['count'] = $this->getAdapter()->aggregation($builder, $aggregationField);
        }

        return $aggregations;
    }

    /**
     * @param array $aggregations
     *
     * @return $this
     */
    protected function setAggregations(array $aggregations): self
    {
        $this->aggregation = $aggregations;

        return $this;
    }

    /**
     * @param array $rawAggregations
     *
     * @return array
     */
    protected function processAggregations(array $rawAggregations): array
    {
        $data = [];
        // todo need refactor
        foreach ($rawAggregations as $aggrName => $aggrTypes) {
            foreach ($aggrTypes as $aggrTypeName => $items) {
                foreach ($items as $item) {
                    $data[$aggrName][$item[$aggrName]] = $item[$aggrTypeName];
                }
            }
        }

        return $data;
    }

    /**
     * @return array
     */
    public function getAggregation(): array
    {
        return $this->aggregation;
    }

    /**
     * @param array $batch
     * @param bool $reloadResults
     * @param bool $insertOrIgnore
     *
     * @return $this
     *
     * @throws MysqlCallbackException
     */
    public function bulkCreate(array $batch, bool $reloadResults = false, bool $insertOrIgnore = false): static
    {
        $this->validateBulkInsertData($batch);

        /** @var MysqlCollection $collection */
        $collection = $this->factoryCreate($batch);
        $dataModel = $collection->getModel();
        $resourceModel = $dataModel->getResource();
        $dataCount = count($batch);

        $collection->bulkBeforeSave();

        $bulkData = [];
        /** @var AbstractMysqlModel $item */
        foreach ($collection as $item) {
            $bulkData[] = $resourceModel->prepareDataForSave($item, null);
        }

        /**
         * Warning:
         * When using MySQL or MariaDB while inserting multiple rows in a single query
         * (INSERT INTO table (a,b,c) VALUES (1,2,3), (2,3,4), ...) to a table with auto_increment column,
         * PDO::lastInsertId does NOT return the autogenerated id of the last row.
         * Instead, the FIRST generated id is returned.
         * This may very well be explained by taking a look at MySQL and MariaDB's documentation.
         */
        $firstInsertId = $this->getAdapter()->insertArray($resourceModel->getMainTable(), $bulkData, $insertOrIgnore);

        if (!$firstInsertId) {

            return $collection;
        }

        if ($reloadResults) {
            $collection
                ->clear()
                ->addFieldToFilter($dataModel->getIdFieldName(), [SearchCriteria::GTE => $firstInsertId])
                ->limit($dataCount)
                ->load();
        }

        $collection->bulkAfterSave();

        return $collection;
    }

    /**
     * Batch must contain unique field values in each item for bulkUpsert to work properly, if this is not possible,
     * then use either bulkCreate or bulkUpdate or bulkUpdateRaw
     *
     * @param array $batch
     * @param string $uniqueField
     *
     * @return $this
     *
     * @throws MysqlUpsertException
     */
    public function bulkUpsert(array $batch, string $uniqueField = AbstractModel::ID_FIELD_NAME): static
    {
        $this->bulkBeforeUpsert($batch, $uniqueField);

        $collection = $this->factoryCreate([], $uniqueField);
        $dataModel = $this->getModel();
        /** @var AbstractMysqlResourceModel $resourceModel */
        $resourceModel = $dataModel->getResource();

        $entityColumns = $resourceModel->getEntityColumns();
        $affectedFields = [];
        foreach ($batch as $batchItem) {
            foreach($batchItem as $fieldName => $fieldValue) {
                if (key_exists($fieldName, $entityColumns)) {
                    $affectedFields[$fieldName] = true;
                }
            }
        }

        $affectedFields = array_keys($affectedFields);
        if ($resourceModel->isEntityDataLockable()) {
            $affectedFields[] = AbstractMysqlResourceModel::LOCKED_ATTRIBUTES_FIELD;
        }

        $uniqueIds = $this->validateUpsertData($batch, $uniqueField);

        $collection
            ->setDefaultFilters()
            ->addIdsFilter($uniqueIds)
            ->addFieldToSelect($affectedFields)
            ->load($uniqueField);

        $entireBatchIsNewData = false;
        if ($collection->isEmpty()) {
            $entireBatchIsNewData = true;
        }

        foreach ($batch as $itemData) {
            $itemId = $itemData[$uniqueField];

            $object = $collection->getItemById($itemId);
            if ($object === null) {
                $object = $dataModel->factoryCreate($itemData);
                $object->isObjectNew(true);
                $collection->addItem($object);

                continue;
            }

            $object->addData($itemData);
        }

        $collection->bulkBeforeSave();

        $canOverrideLockedAttributes = $dataModel->canOverrideLockedAttributes();
        $preparedBulkData = [];
        /** @var AbstractMysqlModel $item */
        foreach ($collection->getItems() as $item) {
            if (!$item->isObjectNew() && !$item->hasDataChanges()) {

                continue;
            }

            $preparedBulkData[] = $resourceModel->prepareDataForUpsert($item, $canOverrideLockedAttributes);
        }

        $updateFields = array_flip($affectedFields);
        unset($updateFields[$uniqueField]);
        if ($entireBatchIsNewData) {
            unset($updateFields[AbstractMysqlResourceModel::LOCKED_ATTRIBUTES_FIELD]);
        }

        $this->getAdapter()->insertOnDuplicateKeyUpdate(
            $resourceModel->getMainTable(),
            $preparedBulkData,
            array_keys($updateFields)
        );

        $collection->bulkAfterSave();

        return $collection;
    }

    /**
     * @param array $batch
     * @param string $idFieldName
     * @return $this
     * @throws EmptyCollectionException
     * @throws MysqlUpsertException
     */
    public function bulkUpdate(array $batch, string $idFieldName = AbstractModel::ID_FIELD_NAME): static
    {
        $ids = $this->validateBulkUpdateData($batch, $idFieldName);

        $dataModel = $this->getModel();
        $resourceModel = $dataModel->getResource();
        /** @var MysqlCollection $collection */
        $collection = $this->factoryCreate([], $idFieldName);

        $row = reset($batch);
        $updateFields = array_keys($row);

        $collection
            ->addIdsFilter($ids)
            ->load($idFieldName);

        if ($collection->isEmpty()) {
            throw new EmptyCollectionException();
        }

        foreach ($batch as $itemData) {
            $itemId = $itemData[$idFieldName];
            $object = $collection->getItemById($itemId);
            if ($object === null) {

                continue;
            }

            $object->addData($itemData);
        }

        $collection->bulkBeforeSave();

        $canOverrideLockedAttributes = $dataModel->canOverrideLockedAttributes();
        $preparedBulkData = [];
        /** @var AbstractMysqlModel $item */
        foreach ($collection->getItems() as $item) {
            if (!$item->hasDataChanges()) {

                continue;
            }
            $preparedBulkData[] = $resourceModel->prepareDataForSave($item, $canOverrideLockedAttributes,
                $updateFields);
        }

        $this->getAdapter()->updateRecordsByArray(
            $resourceModel->getMainTable(),
            $preparedBulkData,
            $idFieldName
        );

        $collection->bulkAfterSave();

        return $collection;
    }

    /**
     * Update records with specified data by specified conditions
     * @param array $data
     * @param array $conditions
     *
     * @return int
     *
     * @throws MysqlCallbackException
     */
    public function bulkUpdateRaw(array $data, array $conditions): int
    {
        if (empty($data)) {

            return 0;
        }

        /** @var AbstractMysqlResourceModel $resourceModel */
        $resourceModel = $this->getModel()->getResource();

        return $this->getAdapter()->updateRecords(
            $resourceModel->getMainTable(),
            $resourceModel->prepareDataForSave(new DataObject($data), null),
            $conditions
        );
    }

    /**
     * @param array  $batch
     * @param string $uniqueField
     *
     * @return array
     *
     * @throws \InvalidArgumentException
     */
    protected function validateUpsertData(array &$batch, string $uniqueField = AbstractModel::ID_FIELD_NAME): array
    {
        if (empty($batch)) {
            throw new \InvalidArgumentException('Bulk data is missing');
        }

        $uniqueIds = array_column($batch, $uniqueField);
        if (empty($uniqueIds)) {

            throw new \InvalidArgumentException('Bulk upsert data is not valid: no unique values found in the batch');
        }

        if (count($uniqueIds) != count($batch)) {

            throw new \InvalidArgumentException('Bulk upsert data is not valid: not all items have unique field');
        }

        $this->validateBulkInsertData($batch);

        return $uniqueIds;
    }

    /**
     * @param array $batch
     *
     * @return bool
     */
    protected function validateBulkInsertData(array $batch): bool
    {
        if (empty($batch)) {
            throw new \InvalidArgumentException('Bulk data is missing');
        }

        $attributeCodes = $this->getModel()->getResource()->getRequiredAttributeCodes();
        foreach ($batch as $key => $item) {
            $missingRequiredAttrs = array_diff($attributeCodes, array_keys($item));

            if (empty($missingRequiredAttrs)) {
                continue;
            }

            throw new \InvalidArgumentException(sprintf(
                'Bulk upsert data is not valid: missing required attribute(s) [%s] for item %s',
                implode(', ', $missingRequiredAttrs),
                $key
            ));
        }

        return true;
    }

    /**
     * @param array  $batch
     * @param string $idFieldName
     *
     * @return array
     */
    protected function validateBulkUpdateData(array &$batch, string $idFieldName = AbstractModel::ID_FIELD_NAME): array
    {
        if (empty($batch)) {
            throw new \InvalidArgumentException('Bulk data is missing');
        }

        $ids = array_column($batch, $idFieldName);
        if (empty($ids)) {

            throw new \InvalidArgumentException('Bulk update data is not valid: no IDs specified');
        }

        if (count($ids) != count($batch)) {

            throw new \InvalidArgumentException('Bulk update data is not valid: not all items have ID field');
        }

        return $ids;
    }

    /**
     * Called at the beginning of bulkUpsert() method. Usually used to validate/prepare input batch data
     *
     * @param array  $batch
     * @param string $uniqueField
     *
     * @return $this
     */
    public function bulkBeforeUpsert(array &$batch, string $uniqueField = AbstractModel::ID_FIELD_NAME): static
    {
        return $this;
    }

    /**
     * Called right before persisting data into database. Usually used to prepare business data
     *
     * @return $this
     */
    public function bulkBeforeSave(): static
    {
        /** @var AbstractMysqlModel $item */
        foreach ($this->getItems() as $item) {
            $item->prepareData();
        }

        return $this;
    }

    /**
     * Called right after persisting data into database
     * Update items data with original data if it is locked
     *
     * @return $this
     */
    public function bulkAfterSave(): static
    {
        $isEntityLockable = $this->getModel()->getResource()->isEntityDataLockable();

        /** @var AbstractMysqlModel $item */
        foreach ($this->getItems() as $item) {
            if ($isEntityLockable) {
                $item->prepareLockedAttributes();
            }

            $item->afterSave();
        }

        return $this;
    }

    /**
     * @return $this
     */
    public function setDefaultFilters(): self
    {
        return $this;
    }

    /**
     * @param array $searchCriteria
     *
     * @return $this
     * @throws \Exception
     */
    public function setSearchCriteria(array $searchCriteria): static
    {
        parent::setSearchCriteria($searchCriteria);

        if (isset($searchCriteria[SearchCriteria::DATA_FIELDS])) {
            $this->addFieldToMultiSelectType($searchCriteria[SearchCriteria::DATA_FIELDS]);
        }

        if (isset($searchCriteria[QueryBuilder::AGGREGATION])) {
            $this->aggregationsToSelect = $searchCriteria[QueryBuilder::AGGREGATION];
        }

        if (isset($searchCriteria[QueryBuilder::SCROLL])) {
            $this->setScrollLastId($searchCriteria[QueryBuilder::SCROLL][QueryBuilder::SCROLL_LAST_ID] ?? 0);
            $this->setLimit($searchCriteria[QueryBuilder::SCROLL][SearchCriteria::LIMIT] ?? SearchCriteria::DEFAULT_PAGE_SIZE);
        }

        if (isset($searchCriteria[SearchCriteria::PAGINATION])) {
            $this->useTotalCount();
        }

        return $this;
    }

    /**
     * @return bool|\PDOStatement
     */
    public function getFetch(): bool|\PDOStatement
    {
        return $this->getAdapter()->fetch($this->getPreparedQueryBuilder());
    }

    /**
     * @return \Generator
     */
    public function lazyLoad(): \Generator
    {
        return $this->getAdapter()->cursor($this->getPreparedQueryBuilder());
    }

    /**
     * @return Builder
     *
     * @throws BindingResolutionException
     */
    protected function getPreparedQueryBuilder(): Builder
    {
        $table = $this->getModel()->getResource()->getMainTable();
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = app()->makeWith(
            QueryBuilder::class, [
            'table' => $table,
        ]);

        $columnTypes = $this->getAdapter()->getColumnTypes($table);

        $queryBuilder->addFilterQuery($this->getFilterGroups(), $columnTypes);
        if (empty($this->getDistinct())) {
            $queryBuilder->addSelect($this->getFieldsToSelect(), $this->getModel()::ID_FIELD_NAME);
        }

        $queryBuilder->addSelectRaw($this->getExpressionsToSelect());
        $queryBuilder->addOrders($this->getOrders());
        $queryBuilder->addGroupsBy(...$this->getGroupsBy());
        // todo implement having
        $queryBuilder->addJoins($this->getJoins());
        $queryBuilder->addFromRaw($this->getFromRaw());
        $queryBuilder->addWhere($this->getWhere());
        $queryBuilder->addWhereIn($this->getWhereIn());
        $queryBuilder->addWhereNull($this->getWhereNull(), $this->getWhereNullCondition());
        $queryBuilder->addOrWhere($this->getOrWhere());
        $queryBuilder->addDistinct($this->getDistinct());
        $queryBuilder->addScrollLastId($this->getScrollLastId());
        $queryBuilder->addLimit($this->getLimit(), $this->getOffset());

        return $queryBuilder->build();
    }

    /**
     * @alias addFieldToSelect
     *
     * @param array|string[] $values
     *
     * @return $this
     */
    public function select(array $values = ['*']): self
    {
        $this->addFieldToSelect($values);

        return $this;
    }

    /**
     * @return int
     *
     * @throws BindingResolutionException
     * @throws MysqlCallbackException
     * @throws \Levtechdev\SimPaas\Exceptions\EntityNotValidException
     */
    public function deleteRecords(): int
    {
        return $this->getAdapter()->delete($this->getPreparedQueryBuilder());
    }

    /**
     * @param string $expression
     * @param array  $bindings
     *
     * @return $this
     */
    public function addExpressionToSelect(string $expression, array $bindings = []): static
    {
        $this->expressionsToSelect[] = [
            'expression' => $expression,
            'bindings'   => $bindings
        ];

        return $this;
    }

    /**
     * @return mixed
     */
    protected function getExpressionsToSelect(): array
    {
        return $this->expressionsToSelect;
    }

    /**
     * @return int
     *
     * @throws BindingResolutionException
     */
    public function getSize(): int
    {
        if ($this->isLoaded()) {

            return $this->count();
        }

        return $this->getAdapter()->countRecords($this->getPreparedQueryBuilder());
    }

    /**
     * @param string|\Closure $column operator for current support Closure must be null
     * @param mixed           $value
     * @param string|null     $operator
     *
     * @return $this
     */
    public function where(string|\Closure $column, mixed $value = null, ?string $operator = '='): self
    {
        $this->where[] = [
            'column'   => $column,
            'value'    => $value,
            'operator' => $operator
        ];

        return $this;
    }

    /**
     * @return array
     */
    protected function getWhere(): array
    {
        return $this->where;
    }

    /**
     * @param array  $columns
     * @param string $condition
     *
     * @return $this
     */
    public function whereNull(array $columns, string $condition = SearchCriteria::CONDITION_AND): self
    {
        $this->whereNull = array_unique(array_merge($this->whereNull, $columns));
        $this->whereNullCondition = $condition;

        return $this;
    }

    /**
     * @return array
     */
    protected function getWhereNull(): array
    {
        return $this->whereNull;
    }

    /**
     * @return string
     */
    protected function getWhereNullCondition(): string
    {
        return $this->whereNullCondition;
    }

    /**
     * @param string $column
     * @param array  $values
     * @param string $condition
     * @param bool   $not
     *
     * @return $this
     */
    public function whereIn(
        string $column,
        array  $values,
        string $condition = SearchCriteria::CONDITION_AND,
        bool   $not = false
    ): self {
        $this->whereIn[] = [
            'column'  => $column,
            'values'  => array_values(array_unique($values)),
            'boolean' => $condition,
            'not'     => $not
        ];

        return $this;
    }

    /**
     * @return array
     */
    protected function getWhereIn(): array
    {
        return $this->whereIn;
    }

    /**
     * @param string $column
     * @param string $operator
     * @param mixed $value
     *
     * @return $this
     */
    public function orWhere(string $column, string $operator = null, mixed $value = null): self
    {
        $this->orWhere[] = [
            'column'   => $column,
            'operator' => $operator,
            'value'    => $value
        ];

        return $this;
    }

    /**
     * @return array
     */
    protected function getOrWhere(): array
    {
        return $this->orWhere;
    }

    /**
     * @param string $column
     * @param string $direction
     *
     * @return $this
     */
    public function orderBy(string $column, string $direction = 'asc'): self
    {
        $this->setOrder($column, $direction);

        return $this;
    }

    /**
     * @param int $limit
     * @param int $offset
     *
     * @return $this
     */
    public function limit(int $limit, int $offset = -1): self
    {
        $this->setLimit($limit);
        $this->offset = $offset;

        return $this;
    }

    /**
     * @return float|int
     */
    protected function getOffset(): float|int
    {
        if ($this->offset == -1) {

            return ($this->getPage() - 1) * $this->getLimit();
        }

        return $this->offset;
    }

    /**
     * @param string          $table
     * @param \Closure|string $first
     * @param string|null     $operator
     * @param string|null     $second
     * @param string          $type
     * @param bool            $where
     *
     * @return $this
     */
    public function join(
        string $table,
        \Closure|string $first,
        string $operator = null,
        ?string $second = null,
        string $type = 'inner',
        bool $where = false
    ): self {
        // todo builder
        $this->joins[] = [
            'table'    => $table,
            'first'    => $first,
            'operator' => $operator,
            'second'   => $second,
            'type'     => $type,
            'where'    => $where
        ];

        return $this;
    }

    /**
     * @return array
     */
    protected function getJoins(): array
    {
        return $this->joins;
    }

    /**
     * @param string ...$values
     *
     * @return $this
     */
    public function groupBy(...$values): self
    {
        foreach ($values as $value) {
            $this->groupsBy[] = $value;
        }

        return $this;
    }

    /**
     * @return array
     */
    protected function getGroupsBy(): array
    {
        return $this->groupsBy;
    }

    /**
     * @param string[]|string $field
     *
     * @return $this
     */
    public function excludeFieldFromSelect(array|string $field): self
    {
        if (empty($field)) {

            return $this;
        }

        if (is_array($field)) {
            foreach ($field as $value) {
                $this->excludeFieldFromSelect($value);
            }
        } else {
            $this->fieldsToExclude[$field] = true;
        }

        return $this;
    }

    /**
     * @return array
     */
    public function getMappedData(): array
    {
        $data = [];
        if ($this->count() > 0) {
            foreach ($this->getItems() as $item) {
                $data[] = $item->getMappedData();
            }
        }

        return $data;
    }

    /**
     * @param int $lastId
     *
     * @return $this
     */
    public function setScrollLastId(int $lastId): self
    {
        $this->scrollLastId = $lastId;

        return $this;
    }

    /**
     * @return int|string
     */
    public function getScrollLastId(): int|string
    {
        return $this->scrollLastId;
    }

    /**
     * @param string $expression
     * @param array  $bindings
     *
     * @return $this
     */
    public function fromRaw(string $expression, array $bindings = []): self
    {
        $this->fromRaw[] = [
            'expression' => $expression,
            'bindings'   => $bindings
        ];

        return $this;
    }

    /**
     * @param string|\Closure $column operator for current support Closure must be null
     *
     * @return $this
     */
    public function distinct(string|\Closure $column): self
    {
        $this->addExpressionToSelect($column);
        $this->distinct[] = $column;

        return $this;
    }

    /**
     * @return array
     */
    public function getDistinct(): array
    {
        return $this->distinct;
    }

    /**
     * @return array
     */
    public function getFromRaw(): array
    {
        return $this->fromRaw;
    }

    /**
     * @param array $group
     *
     * @return $this
     */
    protected function addFilterGroups(array $group): static
    {
        $fields = array_column($group['group'], 'field');
        foreach ($fields as $field) {
            $this->validateJsonField($field);
        }

        $this->filterGroups[] = $group;

        return $this;
    }

    /**
     * Added a field to select
     * and added a field as an expression if necessary (price.msrp -> "SELECT price->'$.msrp'")
     *
     * @param array $fields
     *
     * @return $this
     * @throws \Exception
     */
    protected function addFieldToMultiSelectType(array $fields): self
    {
        foreach ($fields as $field) {
            if (!str_contains($field, '.')) {
                $this->addFieldToSelect($field);

                continue;
            }

            $this->validateJsonField($field);

            $parts = explode('.', $field);
            $fieldProperty = array_pop($parts);
            $field = str_replace('.', '->\'$.', $field) . '\' as ' . $fieldProperty;
            $this->addExpressionToSelect($field);
        }

        if (isset($fieldProperty) && empty($this->getFieldsToSelect())) {
            // Replace 'select * ...' if only the expressions fields were added
            $this->addFieldToSelect($this->getIndexField());
        }

        return $this;
    }

    /**
     * @param string $field
     *
     * @return $this
     * @throws BadRequestException
     */
    protected function validateJsonField(string $field): self
    {
        $parts = explode('.', $field);
        $fieldName = array_shift($parts);

        $table = $this->getModel()->getResource()->getMainTable();
        $fieldTypes = $this->getAdapter()->getColumnTypes($table);
        $fieldType = $fieldTypes[$fieldName] ?? '';
        if (empty($fieldType)) {

            throw new BadRequestException(sprintf('Field %s does not exist in the table %s', $fieldName, $table));
        }

        if ($fieldType != AbstractMysqlResourceModel::JSON_FIELD_TYPE) {

            return $this;
        }

        $jsonFieldsAllowToSearch = $this->getModel()::FILTERABLE_JSON_FIELDS;
        if (empty($jsonFieldsAllowToSearch)) {

            throw new BadRequestException('Search on JSON fields is not available for this entity');
        }

        if (!in_array($fieldName, $jsonFieldsAllowToSearch)) {

            throw new BadRequestException(sprintf(
                'Filtering by %s field is not supported on table %s', $fieldName, $table
            ));
        }

        return $this;
    }

    /**
     * @return array
     * @todo implement ability to select fields using "AS" logic so that selected fields can come back from MySQL with
     *       aliases
     *
     */
    protected function getFieldsToSelect(): array
    {
        return parent::getFieldsToSelect();
    }

    /**
     * @return string
     * @throws BindingResolutionException
     */
    public function __toString()
    {
        return $this->getPreparedQueryBuilder()->toSql();
    }
}

