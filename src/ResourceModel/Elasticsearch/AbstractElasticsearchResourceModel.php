<?php
namespace Levtechdev\Simpaas\ResourceModel\Elasticsearch;

use Elasticsearch\Common\Exceptions\NoNodesAvailableException;
use Illuminate\Support\Arr;
use Levtechdev\Simpaas\Database\DbAdapterInterface;
use Levtechdev\Simpaas\Database\Elasticsearch\Builder\QueryBuilder;
use Levtechdev\Simpaas\Database\Elasticsearch\ElasticSearchAdapter;
use Levtechdev\Simpaas\Database\SearchCriteria;
use Levtechdev\Simpaas\Exceptions\CouldNotDeleteEntity;
use Levtechdev\Simpaas\Exceptions\CouldNotSaveEntity;
use Levtechdev\Simpaas\Exceptions\EntityFieldNotUniqueException;
use Levtechdev\Simpaas\Exceptions\EntityNotFoundException;
use Levtechdev\Simpaas\Exceptions\NotImplementedException;
use Levtechdev\Simpaas\Exceptions\StorageSourceMissingException;
use Levtechdev\Simpaas\Helper\DateHelper;
use Levtechdev\Simpaas\Model\AbstractModel;
use Levtechdev\Simpaas\Model\DataObject;
use Levtechdev\Simpaas\Model\Elasticsearch\AbstractElasticsearchModel;
use Levtechdev\Simpaas\ResourceModel\AbstractResourceModel;
use Throwable;

class AbstractElasticsearchResourceModel extends AbstractResourceModel
{
    const MAX_RESULTS_LIMIT              = 10000;
    const INDEX_BY_CHANNEL_PREFIX        = 'channel_';
    const ETL_LOCK_FILE_MASK             = 'etl_%s_lock';
    const ETL_LOCK_GRACEFUL_TIME         = 60; // seconds
    const READ_ALIAS_NAME_MASK           = '%s%s_read_alias';
    const WRITE_ALIAS_NAME_MASK          = '%s%s_write_alias';
    const MAX_INDEX_STATUS_CHEK_ATTEMPTS = 10;
    const INDEX_STATUS_READY             = 'green';

    const INDEX = [
        'name'           => 'catalog',
        'settings'       => [
            'number_of_shards'                   => 3,
            'number_of_replicas'                 => 2,
            'index.write.wait_for_active_shards' => 'all'
        ],
        'reindex_slices' => 3,
    ];

    /** @var string */
    protected string $connectionName = 'default';

    /** @var array */
    protected array $indexStatusCheckAttempts = [];

    /**
     * Flag to determine if JSON Schema definition must be used to generate ElasticSearch index config (mapping,
     * dynamic templates etc)
     *
     * @var bool
     */
    protected bool $useJsonSchemaConfig = true;

    /** @var array  */
    protected array $mapping = [];

    /** @var DataObject  */
    protected DataObject $defaultValues;

    /** @var array  */
    protected array $uniqueFields = [];

    /** @var array  */
    protected array $systemFields = [];

    /** @var array  */
    protected array $dynamicTemplates = [];

    /** @var DataObject */
    protected DataObject $analyzers;

    /** @var string|null  */
    protected ?string $etlLockFilePath = null;

    public function __construct(ElasticSearchAdapter $dbAdapter, protected JsonSchemaConfig $jsonSchemaConfig)
    {
        if ($this->useJsonSchemaConfig) {
            $this->configureEntity();
        }

        $this->adapter = $dbAdapter->setConnection($this->getConnectionName());
    }

    /**
     * @return DbAdapterInterface|ElasticSearchAdapter
     */
    public function getAdapter(): DbAdapterInterface|ElasticSearchAdapter
    {
        return $this->adapter->setConnection($this->getConnectionName());
    }

    /**
     * @param AbstractModel|AbstractElasticsearchModel $object
     * @param array|int|string $ids
     *
     * @return int
     */
    public function exists(AbstractModel|AbstractElasticsearchModel $object, array|int|string $ids): int
    {
        $ids = array_unique((array)$ids);
        $query = $this->getPreparedQuery([
            SearchCriteria::FILTER => [
                [
                    'group' => [
                        [
                            'field'    => $this->getAdapter()::DOCUMENT_ID_FIELD_NAME,
                            'operator' => SearchCriteria::IN,
                            'value'    => $ids
                        ],
                    ],
                ]
            ]
        ]);

        return $this->getAdapter()->countDocs($this->getReadAlias(), $query);
    }

    /**
     * @param AbstractModel $object
     * @param float|array|bool|int|string|null $ids
     * @param string $fieldName
     *
     * @return int
     *
     * @throws NotImplementedException
     */
    public function existsByField(AbstractModel $object, float|array|bool|int|string|null $ids, string $fieldName): int
    {
        throw new NotImplementedException();
    }

    /**
     * @param AbstractModel $object
     * @param float|bool|int|string|null $id
     * @param string $field
     * @param array $excludedFields
     *
     * @return void
     *
     * @throws EntityNotFoundException
     */
    protected function objectLoad(
        AbstractModel $object,
        float|bool|int|string|null $id,
        string $field = AbstractModel::ID_FIELD_NAME,
        array $excludedFields = []
    ): void {

        if ($field === null || $field == AbstractModel::ID_FIELD_NAME) {
            $field = ElasticSearchAdapter::DOCUMENT_ID_FIELD_NAME;
        }
        $data = $this->getAdapter()->getDoc($this->getReadAlias(), $id, $field, $excludedFields);

        if (empty($data['_source'])) {
            throw new EntityNotFoundException(
                sprintf('Entity "%s" was not found by "%s" = "%s" condition', $object::ENTITY, $field, $id)
            );
        }

        $object->setData((array)$data['_source']);
        $object->setId($data['_id']);
    }

    /**
     * @param AbstractModel $object
     *
     * @return void
     *
     * @throws CouldNotSaveEntity
     */
    protected function objectSave(AbstractModel $object): void
    {
        $data = $this->prepareDataForPersistence($object);

        /**
         * @todo updateDoc() cannot be used in CDMS when updating multidimensional objects in documents
         *       because it will only update matching elements, but not override them.
         *       To fix that issue addDoc() will always be used now but it is now dangerous to call save()
         *       on partially loaded/data set object as it will override entire document with partial data.
         *       It must be reimplemented where changed data must be updated using ES script based Update API,
         *       and if entity doesn't exist ONLY then use addDoc()
         */
        $result = $this->getAdapter()->addDoc(
            $this->getWriteAlias(),
            $data,
            $object->hasSignificantChanges() ? ['refresh' => ElasticSearchAdapter::REFRESH_WAIT_FOR] : [],
            $object->getId()
        );

        $validResult = $result['result'] ?? false;
        if (!in_array($validResult, ['created', 'updated', 'noop']) || empty($result['_id'])) {
            throw new CouldNotSaveEntity();
        }
    }

    /**
     * @param AbstractModel $object
     *
     * @return void
     *
     * @throws CouldNotDeleteEntity
     */
    protected function objectDelete(AbstractModel $object): void
    {
        /**
         * enforceWriteConnection triggers ?refresh=wait_for that is needed so that immediate afterDelete logic works properly in data model
         */
        $result = $this->getAdapter()->enforceWriteConnection(function () use ($object) {
            return $this->getAdapter()->deleteDoc($this->getWriteAlias(), $object->getId());
        });

        $validResult = $result['result'] ?? false;
        if ($validResult != 'deleted') {
            throw new CouldNotDeleteEntity();
        }
    }

    /**
     * Get entity resource name
     *
     * Used in JSON schemas and as prefix to ES index name
     *
     * @return string
     */
    public function getResourceName(): string
    {
        return static::INDEX['name'];
    }

    /**
     * Get ES read index alias
     *
     * Used when reading data from ES - relay between old and new index
     *
     * @return mixed
     */
    public function getReadAlias(): string
    {
        return sprintf(self::READ_ALIAS_NAME_MASK, $this->getResourceName(), $this->getChannelId());
    }

    /**
     * Get ES write index alias
     *
     * Used when writing data to new ES index and during reindexation.
     * By default it points to the same index as read alias
     *
     * @return mixed
     */
    public function getWriteAlias(): string
    {
        return sprintf(self::WRITE_ALIAS_NAME_MASK, $this->getResourceName(), $this->getChannelId());
    }

    /**
     * @return string
     *
     * @deprecated use getWriteAlias() or getReadAlias() instead
     *
     */
    public function getIndexName(): string
    {
        return $this->getWriteAlias();
    }

    /**
     * Create new index based on auto-generated name. Add read/write aliases if the do not exist
     * Method usually used for the first index initialization process or when creating index w/o aliases
     *
     * @return string|bool
     *
     * @throws \Exception
     */
    public function createIndex(): string|bool
    {
        $newIndexName = $this->generateIndexName();

        $result = $this->getAdapter()->createIndex(
            $newIndexName,
            $this->getIndexConfig(),
            $this->getWriteAlias(),
            $this->getReadAlias()
        );

        if (!empty($result['acknowledged'])) {

            return $result['index'];
        }

        return false;
    }

    /**
     * @param bool $deleteOldIndex
     *
     * @return string
     *
     * @throws StorageSourceMissingException
     *
     * @throws Throwable
     */
    public function reindex(bool $deleteOldIndex = false): string
    {
        $newIndexName = $this->generateIndexName();
        $existingIndexName = current($this->getExistingIndexes());
        if ($existingIndexName === false) {
            throw new StorageSourceMissingException($this->getResourceName());
        }

        $this->getAdapter()->reindex(
            $existingIndexName,
            $newIndexName,
            $this->getIndexConfig(),
            $this->getReadAlias(),
            $this->getWriteAlias()
        );

        if ($deleteOldIndex) {
            $this->getAdapter()->deleteIndex($existingIndexName);
        }

        return $newIndexName;
    }

    /**
     * After defined new index as multichannel necessary run migrateIndexToMultiChannelIndex instead of usually reindex
     *
     * @param bool $deleteOldIndex
     *
     * @return string
     *
     * @throws StorageSourceMissingException
     * @throws Throwable
     */
    public function migrateIndexToMultiChannelIndex(bool $deleteOldIndex = false): string
    {
        $newIndexName = $this->generateIndexName();

        $existingIndexName = current(array_keys($this->getAdapter()->getAliasesByName(
            sprintf(self::READ_ALIAS_NAME_MASK, $this->getResourceName(), '') . ',' .
            sprintf(self::WRITE_ALIAS_NAME_MASK, $this->getResourceName(), '')
        )));

        if ($existingIndexName === false) {

            throw new StorageSourceMissingException($this->getResourceName());
        }

        $this->getAdapter()->reindex(
            $existingIndexName,
            $newIndexName,
            $this->getIndexConfig(),
            $this->getReadAlias(),
            $this->getWriteAlias()
        );

        if ($deleteOldIndex) {
            $this->getAdapter()->deleteIndex($existingIndexName);
        }

        return $newIndexName;
    }

    /**
     * Reindex data for specified index by filling data via callback method (doesn't use ES reindex API)
     * Zero downtime approach must be controlled by invoker
     *
     * @todo Locking:
     * each full ETL run should also notify about itself in locking index so that any partial will not run anymore
     * but etl full run also can be killed - not allowing the sate to be reset
     *
     * Index merge command will also lead to a processors kill which will lead to lost messages
     * This happens only on those messages which we do ack before actual processing - batch processors
     * - we need ack only after process - this may lead to not inertly removed products
     *
     * @todo Do not go to maintenance mode when merging - simply lock writes - easy for ETL index but for products
     *       index - not for products index we may simply return 503 on any delete/put/post APIs and return errors on
     *       any write attempts when running any commands/code the same should be done on any raw APIs
     *
     * CDMS 503 during merges - is killing Magento SEO, because a lot of pages will show 404
     *
     * @todo Do not run ETL if merge is running (when maintenance mode will be removed from merging logic)
     */
    /**
     * 1. Generate new index name
     * 2. Create ETL locking flag to indicate that currently full ETL is in progress
     *    and so that other ETL processes are blocked
     * 3. Create a new index with the current timestamp
     * 4. Re-point resource_write alias to the new index, remove old alias pointer - use $this->repointAlias()
     *   Invoker must pause all data writes for a moment of data indexation - that will allow
     *   to make sure that we will not accidentally create partial documents and lose parent to child ID based
     *   relations when updating documents technically saying we should not reindex often and if we do - we should
     *   pause any writes
     * 5. Copy (reindex data) via $etlCallback invocation
     * 6. After filling all data, re-point read alias (resource_read) to the newly created index
     * 7. Remove the old index - not implemented here, can be implemented by invoker
     * 8. Remove ETL locking flag
     * 9. Optionally: remove old index, but only if ETL completed successfully
     *
     * @see https://engineering.carsguide.com.au/elasticsearch-zero-downtime-reindexing-e3a53000f0ac
     *
     * @param \Closure|array $etlCallback
     * @param bool           $deleteOldIndex
     *
     * @return string
     *
     * @throws StorageSourceMissingException
     * @throws Throwable
     */
    public function reindexByETLCallback(\Closure|array $etlCallback, bool $deleteOldIndex = false): string
    {
        $existingIndexName = current($this->getExistingIndexes());
        if ($existingIndexName === false) {
            throw new StorageSourceMissingException($this->getResourceName());
        }

        if ($this->isETLLocked()) {
            throw new \Exception(sprintf('Another ETL process is running for %s', $this->getResourceName()));
        }
        $this->lockETL();

        // Graceful waiting to ensure ETL lock file is available across all servers as it will be created on NFS share
        // Plus we ensure that all Frontend Product queue workers finished correctly their processing
        sleep(self::ETL_LOCK_GRACEFUL_TIME);

        $newIndexName = $this->generateIndexName();
        $readAlias = $this->getReadAlias();
        $writeAlias = $this->getWriteAlias();

        try {
            /**
             * WARNING: do not change any code here. $this->getAdapter() is invoked here multiple times to make sure connecting to correct ES cluster is made
             */
            $this->getAdapter()->createIndex($newIndexName, $this->getIndexConfig());
            $this->getAdapter()->repointWriteAlias($existingIndexName, $newIndexName, $writeAlias);

            try {
                call_user_func($etlCallback);
            } catch (Throwable $e1) {
                try {
                    // try to restore last write index alias
                    $this->getAdapter()->repointWriteAlias($newIndexName, $existingIndexName, $writeAlias);
                } catch (Throwable $e2) {
                    // throw $e2;
                }
                throw $e1;
            }

            try {
                $this->getAdapter()->repointReadAlias($existingIndexName, $newIndexName, $readAlias);
            } catch (Throwable $e1) {
                try {
                    // try to restore last write index alias
                    $this->getAdapter()->repointWriteAlias($newIndexName, $existingIndexName, $writeAlias);
                } catch (Throwable $e2) {
                    //throw $e2;
                }
                throw $e1;
            }

            if ($deleteOldIndex) {
                $this->getAdapter()->deleteIndex($existingIndexName);
            }
        } catch (Throwable $e) {
            $this->unlockETL();
            throw $e;
        }

        $this->afterETLReindex();
        $this->waitForWriteIndexGreenStatus($newIndexName);

        $this->unlockETL();

        return $newIndexName;
    }

    /**
     * @return $this
     */
    protected function afterETLReindex(): self
    {
        return $this;
    }

    /**
     * @param string $index
     *
     * @return bool
     */
    public function waitForWriteIndexGreenStatus(string $index): bool
    {
        if (!key_exists($index, $this->indexStatusCheckAttempts)) {
            $this->indexStatusCheckAttempts[$index] = 0;
        }
        $this->indexStatusCheckAttempts[$index]++;
        try {
            $status = $this->getAdapter()
                    ->clusterHealth([
                        'index' => $index,
                        'wait_for_status' => 'green',
                        'timeout' => '30s'
                    ])['status'] ?? null;

            if ($status === self::INDEX_STATUS_READY
                || $this->indexStatusCheckAttempts[$index] >= self::MAX_INDEX_STATUS_CHEK_ATTEMPTS
            ) {
                $this->indexStatusCheckAttempts[$index] = 0;

                return true;
            }
        } catch (Throwable $e) {

        }

        return $this->waitForWriteIndexGreenStatus($index);
    }

    /**
     * @return bool
     */
    public function isETLLocked(): bool
    {
        return file_exists($this->getETLLockFilePath());
    }

    /**
     * @todo implement flock($fp, LOCK_EX | LOCK_NB) based locking
     */
    public function lockETL()
    {
        exec('touch ' . $this->getETLLockFilePath());
    }

    /**
     * @return void
     */
    public function unlockETL()
    {
        if (file_exists($this->getETLLockFilePath())) {
            unlink($this->getETLLockFilePath());
        }
    }

    /**
     * @return string|null
     */
    public function getETLLockFilePath(): string|null
    {
        if ($this->etlLockFilePath === null) {
            $this->etlLockFilePath = base_path('storage') . DS .
                sprintf(self::ETL_LOCK_FILE_MASK, $this->getResourceName());
        }

        return $this->etlLockFilePath;
    }

    /**
     * @param bool $deleteOldIndex
     *
     * @return string
     *
     * @throws \Exception
     */
    public function reindexBare(bool $deleteOldIndex = false): string
    {
        $newIndexName = $this->generateIndexName();
        $existingAliases = $this->getAdapter()->getAliasesByName($this->getReadAlias() . ',' . $this->getWriteAlias());

        if (empty($existingAliases)) {

            throw new StorageSourceMissingException($this->getResourceName());
        }

        $this->getAdapter()->reindexBare(
            $existingAliases,
            $newIndexName,
            $this->getIndexConfig(),
            $this->getReadAlias(),
            $this->getWriteAlias()
        );
        if ($deleteOldIndex) {
            foreach ($existingAliases as $indexName => $aliases) {
                $this->getAdapter()->deleteIndex($indexName);
            }
        }

        return $newIndexName;
    }

    /**
     * Update mappings on existing indexes for current entity
     *
     * @return array
     */
    public function updateIndexesMappings(): array
    {
        $updatedIndexNames = [];
        foreach ($this->getExistingIndexes() as $indexName) {
            $mapping = ['mapping' => $this->mapping];
            if (!empty($this->getDynamicTemplates())) {
                $mapping['dynamic_templates'] = $this->getDynamicTemplates();
            }

            $results = $this->getAdapter()->updateIndexMapping($indexName, $mapping);
            if (!empty($results['acknowledged']) && $results['acknowledged'] === true) {
                $updatedIndexNames[] = $indexName;
            }
        }

        return $updatedIndexNames;
    }

    /**
     * @return bool
     */
    public function deleteIndex(): bool
    {
        foreach ($this->getExistingIndexes() as $indexName) {
            $this->getAdapter()->deleteIndex($indexName);
        }

        return true;
    }

    /**
     * @return bool
     */
    public function indexExists(): bool
    {
        return !empty($this->getExistingIndexes());
    }

    /**
     * @return array
     */
    public function getExistingIndexes(): array
    {
        $existingAliases = $this->getAdapter()->getAliasesByName($this->getReadAlias() . ',' . $this->getWriteAlias());

        return array_keys($existingAliases);
    }

    /**
     * @return string
     */
    public function generateIndexName(): string
    {
        return $this->getResourceName() . $this->getChannelId() . '_' . time();
    }

    /**
     * Empty for common indexes
     *
     * @return string
     */
    public function getChannelId(): string
    {
        if (!str_contains($this->getConnectionName(), self::INDEX_BY_CHANNEL_PREFIX)) {

            return '';
        }

        $key = config(sprintf('database.elasticsearch.%s.channel_id', $this->getConnectionName()), '');
        if (!empty($key)) {

            return '_' . $key;
        }

        return '';
    }

    /**
     * @return array
     */
    public function getIndexConfig(): array
    {
        $indexConfig = static::INDEX;
        $indexConfig['mapping'] = $this->getMapping();
        if (!empty($this->getDynamicTemplates())) {
            $indexConfig['dynamic_templates'] = $this->getDynamicTemplates();
        }

        return $indexConfig;
    }

    /**
     * Prepare entity config/settings (fields mapping, default values, unique fields etc.) from json-schema definitions
     *
     * @return void
     */
    protected function configureEntity()
    {
        $entityType = $this->getResourceName();
        $this->mapping = $this->jsonSchemaConfig->getMapping($entityType);
        $this->defaultValues = $this->jsonSchemaConfig->getDefaultValues($entityType);
        $this->analyzers = $this->jsonSchemaConfig->getAnalyzers($entityType);
        $this->uniqueFields = $this->jsonSchemaConfig->getUniqueFields($entityType);
        $this->dynamicTemplates = $this->jsonSchemaConfig->getDynamicTemplates($entityType);
        $this->systemFields = $this->jsonSchemaConfig->getSystemFields($entityType);
    }

    /**
     * @return array
     */
    public function getMapping(): array
    {
        return $this->mapping;
    }

    /**
     * @param array $mapping
     *
     * @return array
     */
    protected function getFieldsMapFromESMapping(array $mapping): array
    {
        $fields = [];
        foreach ($mapping as $fieldName => $data) {
            if (!empty($data['properties'])) {
                $fields[$fieldName] = $this->getFieldsMapFromESMapping($data['properties']);
                continue;
            }

            $fields[$fieldName] = [];
        }

        return $fields;
    }

    /**
     * @return DataObject
     */
    public function getAnalyzers(): DataObject
    {
        return $this->analyzers;
    }

    /**
     * @param $field
     *
     * @return array|null
     */
    public function getAnalyzerByField($field): array|null
    {
        if (empty($this->getAnalyzers()) || !is_string($this->getAnalyzers()->getData($field))) {

            return null;
        }

        return $this->getAnalyzers()->getData($field);
    }

    /**
     * @return DataObject
     */
    public function getDefaultValues(): DataObject
    {
        return $this->defaultValues;
    }

    /**
     * @return array
     */
    public function getSystemFields(): array
    {
        return $this->systemFields;
    }

    /**
     * @return array
     */
    public function getUniqueFields(): array
    {
        return $this->uniqueFields;
    }

    /**
     * @return array
     */
    public function getDynamicTemplates(): array
    {
        return $this->dynamicTemplates;
    }

    /**
     * @return array
     */
    public function getAllowedFields(): array
    {
        $fields = $this->getMapping();
        $fields[AbstractModel::ID_FIELD_NAME] = [];

        if (empty($this->getDynamicTemplates())) {

            return $fields;
        }

        foreach ($this->getDynamicTemplates() as $template) {
            if (empty($template['analysed_string_template']['path_match'])
                || empty($template['analysed_string_template']['mapping'])
            ) {
                continue;
            }

            $fieldPattern = explode('.', $template['analysed_string_template']['path_match']);
            $fields[$fieldPattern[0]] = $template['analysed_string_template']['mapping'];
        }

        return $fields;
    }

    /**
     * Prepare data for ES index: unset non-existing fields in ES index mapping
     *
     * @param DataObject $object
     *
     * @return array
     * @todo for now only a first level of data fields is considered. Inner structure of the fields checking not
     *       implemented yet
     * @todo null_value must be reconsidered -
     *       https://www.elastic.co/guide/en/elasticsearch/reference/1.4/query-dsl-exists-filter.html#_literal_null_value_literal_mapping_2
     *
     */
    public function prepareDataForPersistence(DataObject $object)
    {
        $data = [];
        $fields = $this->getAllowedFields();

        foreach (array_keys($fields) as $field) {
            if ($object->hasData($field)) {
                $fieldValue = $object->getData($field);

                if ($fieldValue !== null) {
                    $data[$field] = $fieldValue;
                } elseif (key_exists('null_value', $fields[$field])) {
                    $data[$field] = $fields[$field]['null_value'] == 'NULL'
                        ? null
                        : $fields[$field]['null_value'];
                }
            }
        }

        unset($data[AbstractModel::ID_FIELD_NAME]);

        return $data;
    }

    /**
     * @param AbstractModel|AbstractElasticsearchModel $object
     *
     * @return $this
     * @throws EntityFieldNotUniqueException
     */
    public function beforeSave(AbstractModel|AbstractElasticsearchModel $object): static
    {
        if ($object->isObjectNew()) {
            $object->isObjectNew(true);
            $object->setId($object->generateUniqueId());
        }

        $this->validateUniqueFields($object);

        parent::beforeSave($object);

        return $this;
    }

    /**
     * @param AbstractElasticsearchModel $object
     *
     * @return bool
     *
     * @throws EntityFieldNotUniqueException
     */
    protected function validateUniqueFields(AbstractElasticsearchModel $object): bool
    {
        if (empty($this->uniqueFields)) {

            return true;
        }

        $result = $this->getAdapter()->search($this->getReadAlias(), $this->prepareUniquenessCheckQuery($object));
        $countHits = $result['hits']['total']['value'] ?? false;

        if ($countHits == 1 && $result['hits']['hits'][0]['_id'] == $object->getId()) {

            return true;
        }

        if (!$countHits) {

            return true;
        }

        $existingIds = [];
        foreach ($result['hits']['hits'] as $data) {
            $existingIds[] = $data['_id'];
        }

        throw new EntityFieldNotUniqueException(
            !$object->isObjectNew() ? $object->getId() : null,
            $existingIds,
            $this->getPreparedUniqueFieldsForDateRetrieval()
        );
    }

    /**
     * Prepare ES query to check entity uniqueness
     *
     * @param AbstractModel $object
     *
     * @return array
     */
    protected function prepareUniquenessCheckQuery(AbstractModel $object): array
    {
        $uniqueFieldsFilter = [];
        foreach ($this->uniqueFields as $field) {
            $uniqueFieldsFilter[] = [
                'field'    => $field,
                'operator' => SearchCriteria::EQ,
                'value'    => $object->getDataUsingMethod($field),
            ];
        }

        $filter = [
            [
                'condition' => count($uniqueFieldsFilter) == 1
                    ? SearchCriteria::CONDITION_AND
                    : SearchCriteria::CONDITION_OR,
                'group'     => $uniqueFieldsFilter
            ]
        ];
        $uniquenessScopeFields = $this->getUniquenessScopeFields();
        if (!empty($uniquenessScopeFields)) {
            foreach ($uniquenessScopeFields as $fieldName) {
                $filter[] = [
                    'group' => [
                        [
                            'field'    => $fieldName,
                            'operator' => SearchCriteria::EQ,
                            'value'    => $object->getDataUsingMethod($fieldName),
                        ]
                    ]
                ];
            }
        }

        return $this->getPreparedQuery([
            SearchCriteria::FILTER      => $filter,
            SearchCriteria::DATA_FIELDS => $this->getPreparedUniqueFieldsForDateRetrieval(),
            SearchCriteria::SIZE        => SearchCriteria::MAX_PAGE_SIZE
        ]);
    }

    /**
     * @return array
     */
    public function getPreparedUniqueFieldsForDateRetrieval(): array
    {
        return $this->uniqueFields;
    }

    /**
     * NOTE: comment from CDMS of
     * class AttributeOption
     * public function getUniquenessScopeFields()
     *   {
     *      return ['attribute_code'];
     * }
     *
     * Attribute options uniqueness must be checked on only on attribute_code scope, not on global
     *
     * @return array
     */
    public function getUniquenessScopeFields(): array
    {
        return [];
    }

    /**
     * @param AbstractElasticsearchModel $object
     * @param array $batch
     *
     * @return array
     *
     * @throws NoNodesAvailableException
     */
    public function addRecords(AbstractElasticsearchModel $object, array $batch): array
    {
        $results = $this->getAdapter()->addDocs($this->getWriteAlias(), $batch);
        if (empty($results['items'])) {

            return [];
        }

        $resultedIds = [];
        foreach ($results['items'] as $docData) {
            $resultedIds[] = $docData['index']['_id'];
        }

        return $resultedIds;
    }

    /**
     * @param array $batch
     *
     * @return array
     *
     * @throws NoNodesAvailableException
     */
    public function updateRecords(array $batch): array
    {
        $results = $this->getAdapter()->addDocs($this->getWriteAlias(), $batch);
        if (empty($results['items'])) {

            return [];
        }

        $resultedIds = [];
        foreach ($results['items'] as $docData) {
            $resultedIds[] = $docData['index']['_id'];
        }

        return $resultedIds;
    }

    /**
     * @param array $filter
     * @param array $updateData
     * @param bool  $ignoreOnConflict
     *
     * @return int
     */
    public function updateRecordsByFilter(array $filter, array $updateData, bool $ignoreOnConflict = false): int
    {
        $resultData = $this->getAdapter()->updateDocsByFilter(
            $this->getWriteAlias(),
            $filter,
            $updateData,
            ['ignore_on_conflict' => $ignoreOnConflict]
        );

        return (int)($resultData['updated'] ?? 0);
    }

    /**
     * @param array $query
     *
     * @return array|callable
     */
    public function deleteRecords(array $query): array|callable
    {
        return $this->getAdapter()->deleteDocsByFilter($this->getWriteAlias(), $query);
    }

    /**
     * @param string $entityId
     * @param array  $attributes
     *
     * @return bool
     */
    public function saveAttributes($entityId, array $attributes)
    {
        $attributes = $this->prepareDataForPersistence(new DataObject($attributes));

        if (empty($attributes['updated_at'])) {
            $attributes['updated_at'] = date(DateHelper::DATE_TIME_FORMAT);
        }
        $result = $this->getAdapter()->updateDoc($this->getWriteAlias(), $entityId, $attributes);
        $validResult = $result['result'] ?? false;

        return $validResult == 'updated';
    }

    /**
     * @param array $attributesData
     *
     * @return array[]
     *
     * @throws NoNodesAvailableException
     */
    public function massSaveAttributes(array $attributesData): array
    {
        if (empty($attributesData)) {
            return [[], []];
        }
        foreach ($attributesData as $entityId => &$attributes) {
            $attributes = $this->prepareDataForPersistence(new DataObject($attributes));
            if (empty($attributes['updated_at'])) {
                $attributes['updated_at'] = date(DateHelper::DATE_TIME_FORMAT);
            }
            $attributes[AbstractModel::ID_FIELD_NAME] = $entityId;
        }

        $results = $this->getAdapter()->updateDocs($this->getWriteAlias(), $attributesData);
        if (empty($results['items'])) {

            return [];
        }

        $resultedIds = [];
        $errors = [];
        foreach ($results['items'] as $docData) {
            $validResult = $docData['update']['result'] ?? false;
            if (!in_array($validResult, ['created', 'updated', 'noop'])) {
                $errors[$docData['update']['_id']] = $docData['update']['error']['reason'] ?? $validResult;
            } else {
                $resultedIds[] = $docData['update']['_id'];
            }
        }

        return [$resultedIds, $errors];
    }

    /**
     * @param string $entityId
     * @param array  $attributes
     *
     * @return int
     */
    public function addAttributes(string $entityId, array $attributes): int
    {
        $attributes = $this->prepareDataForPersistence(new DataObject($attributes));

        $result = $this->getAdapter()->addDocFieldsByScript($this->getWriteAlias(), $entityId, $attributes);

        return (int)($result['updated'] ?? 0);
    }

    /**
     * @param string $entityId
     * @param array  $attributes
     *
     * @return int
     */
    public function updateAttributes(string $entityId, array $attributes): int
    {
        $attributes = $this->prepareDataForPersistence(new DataObject($attributes));

        $result = $this->getAdapter()->updateDocFieldsByScript($this->getWriteAlias(), $entityId, $attributes);

        return (int)($result['updated'] ?? 0);
    }

    /**
     * @param string $entityId
     * @param array  $attributes
     *
     * @return int
     */
    public function removeAttributes(string $entityId, array $attributes): int
    {
        $result = $this->getAdapter()->deleteDocFieldsByScript($this->getWriteAlias(), $entityId, $attributes);

        return (int)($result['updated'] ?? 0);
    }

    /**
     * Retrieve statistic aggregations
     *
     * Currently supported aggregations: min|max|avg|sum
     *
     * @param array $statsFields
     * @param array $filterConditions
     *
     * @return array
     *
     * Example:
     *
     * Input:
     * $statsFields = [
     *     'sum' => ['performance_sum' => 'scores.performance', 'sum_of_my_quality' => 'scores.data_quality'],
     *     'avg' => ['average_performance' => 'scores.performance', 'scores.data_quality'],
     * ]
     *
     * Results:
     * [
     *     // named results will be like this:
     *     'performance_sum' => value,
     *     'sum_of_my_quality' => value,
     *     'average_performance' => value,
     *
     *     // unnamed results:
     *     'scores.data_quality_avg' => value
     * ]
     *
     */
    public function getStats(array $statsFields, array $filterConditions = []): array
    {
        $query = $this->getPreparedQuery([
            SearchCriteria::FILTER     => $filterConditions,
            QueryBuilder::AGGREGATION  => $statsFields,
            // no actual data needed, just aggregations
            SearchCriteria::PAGINATION => [
                'limit' => 0
            ]
        ]);

        $result = $this->getAdapter()->search($this->getReadAlias(), $query);
        $countHits = $result['hits']['total']['value'] ?? false;

        $aggregations = [];
        if ($countHits && !empty($result['aggregations'])) {
            foreach ($result['aggregations'] as $name => $value) {
                $aggregations[$name] = $value['value'];
            }
            $aggregations['total_hits'] = $countHits;
        }

        return $aggregations;
    }

    /**
     * @param array $ids
     * @param array $conditions
     * @param array $fields
     *
     * @return array
     */
    public function fetchDataByIds(array $ids, array $conditions = [], array $fields = []): array
    {
        $entitiesData = [];

        $baseConditions[] = [
            'field'    => ElasticSearchAdapter::DOCUMENT_ID_FIELD_NAME,
            'operator' => SearchCriteria::IN,
            'value'    => $ids
        ];

        $fieldsToSelect = array_values(array_unique(array_merge($fields, [ElasticSearchAdapter::DOCUMENT_ID_FIELD_NAME])));
        $query = $this->getPreparedQuery([
            SearchCriteria::FILTER      => [
                [
                    'group' => array_values(array_merge($baseConditions, $conditions))
                ]
            ],
            SearchCriteria::DATA_FIELDS => $fieldsToSelect,
        ]);

        $scroll = $this->getAdapter()->scroll(
            $this->getReadAlias(),
            $query,
            static::MAX_RESULTS_LIMIT
        );

        $scroll->rewind();
        while ($scroll->valid()) {
            $results = $scroll->current();
            foreach ($results['hits']['hits'] as $hit) {
                $entitiesData[$hit['_id']] = array_merge(['id' => $hit['_id']], $hit['_source'] ?? []);
            }
            $scroll->next();
        }

        $scroll->__destruct();

        return $entitiesData;
    }

    /**
     * @param array $searchCriteria
     *
     * @return array
     */
    protected function getPreparedQuery(array $searchCriteria): array
    {
        $builder = new QueryBuilder($searchCriteria);

        return $builder->build();
    }

    /**
     * @return array
     */
    public function getInlineFieldsMap(): array
    {
        return Arr::dot($this->getFieldsMapFromESMapping($this->getMapping()));
    }
}