<?php
declare(strict_types=1);

namespace Levtechdev\Simpaas\Database\ElasticSearch\Processor;

use Exception;
use InvalidArgumentException;
use JetBrains\PhpStorm\ArrayShape;
use Levtechdev\Simpaas\Model\Elasticsearch\EntityResourceModelMapperInterface;
use Monolog\Logger;
use Elasticsearch\Common\Exceptions\NoNodesAvailableException;
use Throwable;
use Levtechdev\Simpaas\Helper\DateHelper;
use Levtechdev\Simpaas\Model\AbstractModel;
use Levtechdev\Simpaas\Database\DbAdapterInterface;
use Levtechdev\Simpaas\Helper\Logger as LoggerHelper;
use Levtechdev\Simpaas\Database\Elasticsearch\ElasticSearchAdapter;

class BulkProcessor
{
    const LOG_CHANNEL = 'bulk_processor';

    const ES_ACTION_CREATE = 'index';
    const ES_ACTION_UPDATE = 'update';
    const ES_ACTION_DELETE = 'delete';

    // Note: make sure autocommit count is higher than 3X products raw batch count,
    // otherwise products raw data processing will create products for a brief moment w/o parent/children products
    const FORCED_AUTO_COMMIT_ITEMS_COUNT = 900;

    /** @var array */
    protected array $connectionsByIndex = [];

    /** @var array */
    protected array $bulkRegistry = [];

    /** @var array */
    protected array $entityResourceData = [];

    /** @var bool */
    protected bool $logAllQueries = false;

    /** @var Logger */
    protected Logger $logger;

    public function __construct(
        protected ElasticSearchAdapter $adapter,
        protected EntityResourceModelMapperInterface $coreHelper,
        protected LoggerHelper $logHelper
    ) {
        $this->logAllQueries = env('ES_DB_QUERY_DEBUG', false);
        $this->logger = $this->logHelper->getLogger(
            self::LOG_CHANNEL,
            base_path(LoggerHelper::LOGS_DIR . DbAdapterInterface::ERROR_LOG_FILE)
        );
    }

    /**
     * @param string $entityType
     * @param array $batch
     * @param bool $waitForData
     *
     * @return $this
     *
     * @throws NoNodesAvailableException
     * @throws Throwable
     */
    public function addCreateActions(string $entityType, array $batch, bool $waitForData = true): self
    {
        $batch = $this->prepareBatch($entityType, $batch);
        $index = $this->getWriteAliasByEntityType($entityType);
        $connectionName = $this->getConnectionNameByIndex($index);
        foreach ($batch as $data) {
            $this->bulkRegistry[$connectionName][] = $this->buildCreateAction($index, $data);
            if (count($this->bulkRegistry[$connectionName]) >= self::FORCED_AUTO_COMMIT_ITEMS_COUNT) {
                $this->commit($waitForData);
            }
        }

        return $this;
    }

    /**
     * FORMAT batch
     *  [
     *      [
     *          'doc_id'      => 'doc_id'      (document ID)
     *          'nested_id'   => 'nested_id'   (identified of nested object in document)
     *           $nestedPath  => 'updatedData' (nested prop name => updated object)
     *      ]
     * ]
     *
     * @param string $entityType
     * @param array  $batch
     * @param string $nestedPath
     * @param bool   $waitForData
     *
     * @return $this
     * @throws NoNodesAvailableException|Throwable
     */
    public function addUpdateNestedActions(
        string $entityType,
        array $batch,
        string $nestedPath = 'variation',
        bool $waitForData = true
    ): self {
        $this->validateUpdateNestedBatch($batch, $nestedPath);
        $index = $this->getWriteAliasByEntityType($entityType);
        $connectionName = $this->getConnectionNameByIndex($index);

        foreach ($batch as $data) {
            $this->bulkRegistry[$connectionName][] = $this->buildUpdateNestedAction($index, $data, $nestedPath);
            if (count($this->bulkRegistry[$connectionName]) >= self::FORCED_AUTO_COMMIT_ITEMS_COUNT) {
                $this->commit($waitForData);
            }
        }

        return $this;
    }

    /**
     * @param string $index
     * @param $data
     * @param string $nestedPath
     *
     * @return \array[][]
     */
    #[ArrayShape(['body' => "array[]", 'script' => "mixed"])]
    protected function buildUpdateNestedAction(string $index, $data, string $nestedPath): array
    {
        $updatedData = $data[$nestedPath];
        $updatedData['updated_at'] = date(DateHelper::DATE_TIME_FORMAT);

        $sourceScriptTemplate = 'def targets = ctx._source.%1$s.findAll(%1$s -> %1$s.id == params.nested_id); for(%1$s in targets) { %2$s }';

        // build updated data
        $updatedDataString = '';
        foreach ($updatedData as $propName => $propValue) {
            $updatedDataString .= sprintf('variation.%1$s=params.variation.%1$s;', $propName);
        }

        $action = [
            'body' => [
                self::ES_ACTION_UPDATE => [
                    '_index'            => $index,
                    '_id'               => $data['doc_id'],
                    'retry_on_conflict' => 3,
                ],
            ],
        ];

        $action['script'] = [
            'source' => sprintf($sourceScriptTemplate, $nestedPath, $updatedDataString),
            'params' => [
                'nested_id' => $data['nested_id'],
                $nestedPath => $updatedData
            ]
        ];

        return $action;
    }

    /**
     * Batch ID can be: _id, id instead of doc_id, nested_id
     * [['doc_id' => 'dock_id', 'nested_id' => "nested_id", 'field1' => 'field1', 'field2' => 'field3']];
     *
     * @param array  $batch
     * @param string $nestedPath
     *
     * @return $this
     */
    protected function validateUpdateNestedBatch(array $batch, string $nestedPath): self
    {
        foreach ($batch as $item) {
            if (!key_exists('doc_id', $item)) {
                throw new InvalidArgumentException('Cannot add update nested actions: some or all entity doc IDs do not exist in data batch');
            }

            if (!key_exists('nested_id', $item)) {
                throw new InvalidArgumentException('Cannot add update nested actions: some or all entity nested IDs do not exist in data batch');
            }

            if (!key_exists($nestedPath, $item)) {
                throw new InvalidArgumentException('Cannot add update nested actions: some or all entity data do not exist in data batch');
            }
        }

        return $this;
    }

    /**
     * @param string $entityType
     * @param array $batch
     * @param bool $waitForData
     *
     * @return $this
     *
     * @throws NoNodesAvailableException|Throwable
     */
    public function addUpdateActions(string $entityType, array $batch, bool $waitForData = true): self
    {
        $batch = $this->prepareBatch($entityType, $batch);
        $index = $this->getWriteAliasByEntityType($entityType);
        $connectionName = $this->getConnectionNameByIndex($index);

        foreach ($batch as $data) {
            $this->bulkRegistry[$connectionName][] = $this->buildUpdateAction($index, $data);
            if (count($this->bulkRegistry[$connectionName]) >= self::FORCED_AUTO_COMMIT_ITEMS_COUNT) {
                $this->commit($waitForData);
            }
        }

        return $this;
    }

    /**
     * @param string $entityType
     * @param array $batch
     * @param bool $waitForData
     *
     * @return $this
     *
     * @throws NoNodesAvailableException
     * @throws Throwable
     */
    public function addDeleteActions(string $entityType, array $batch, bool $waitForData = true): self
    {
        $this->validateBatch($batch);
        $index = $this->getWriteAliasByEntityType($entityType);
        $connectionName = $this->getConnectionNameByIndex($index);

        foreach ($batch as $data) {
            $this->bulkRegistry[$connectionName][] = $this->buildDeleteAction($index, $data);
            if (count($this->bulkRegistry[$connectionName]) >= self::FORCED_AUTO_COMMIT_ITEMS_COUNT) {
                $this->commit($waitForData);
            }
        }

        return $this;
    }

    /**
     * @param bool $waitForData
     *
     * @return array
     *
     * @throws NoNodesAvailableException
     * @throws Throwable
     */
    public function commit(bool $waitForData = true): array
    {
        if (empty($this->bulkRegistry)) {

            return [];
        }

        $startT = microtime(true);
        $results = [];
        foreach ($this->bulkRegistry as $connectionName => $actions) {
            $query['body'] = [];
            foreach ($actions as $action) {
                $query['body'][] = $action['body'];

                // for deleting not need 'data'
                if (!empty($action['data'])) {
                    $query['body'][] = $action['data'];
                }

                // for nested updating
                if (!empty($action['script'])) {
                    $query['body'][] = ['script' => $action['script']];
                }
            }

            $adapter = $this->getAdapter($connectionName);

            /**
             * ?refresh=wait_for needed so that immediate search can be performed on new documents
             */
            if ($waitForData) {
                $query['refresh'] = ElasticSearchAdapter::REFRESH_WAIT_FOR;
            }
            try {
                $results = array_merge($results, $adapter->getWriteClient()->bulk($query)['items'] ?? []);
            } catch (Throwable $e) {
                $this->logger->error(
                    $e->getMessage(),
                    [
                        'query' => $query,
                        'trace' => $e->getTrace(),
                        'connection_name' => $connectionName
                    ]);

                throw $e;
            }

            if ($this->logAllQueries) {
                $query['bulk_update'] = ['count' => count($query['body']) / 2, 'data_size' => strlen(print_r($query, true))];
            }
            unset($query['body']);
            $adapter->logQuery($query, microtime(true) - $startT);
        }

        $this->bulkRegistry = [];

        if (empty($results)) {

            throw new Exception('No bulk actions were processed');
        }

        $resultedIds = $errors = [];
        foreach ($results as $docData) {
            $action = key($docData);
            $docData = current($docData);

            if ($docData['status'] != 200 && $docData['status'] != 201) {
                $errors[$action][$docData['_index']][$docData['_id']] = $docData['error'] ?? ['reason' => $docData['result']];

                continue;
            }

            $resultedIds[] = $docData['_id'];
        }

        $this->log($errors, $results);

        return [$resultedIds, $errors];
    }

    /**
     * @param array $errors
     * @param array $results
     *
     * @return $this
     */
    protected function log(array $errors, array $results = []): self
    {
        if (empty($errors)) {

            return $this;
        }

        static $slackNotification = true;
        foreach ($errors as $action => $indexErrors) {
            foreach ($indexErrors as $index => $errorItems) {
                $this->logger->warning(
                    sprintf('%s issue: index [%s]', ucfirst($action), $index),
                    $errorItems
                );
            }

            if ($slackNotification) {
                $this->logHelper->notifySlack(
                    'ElasticSearch Data Persistence Error',
                    \Monolog\Logger::CRITICAL
                );
            }

            $slackNotification = false;
        }

        $traceException = new Exception();
        $this->logger->debug('Exception debug', [
            'results' => $results,
            'trace'   => $traceException->getTraceAsString()
        ]);

        return $this;
    }

    /**
     * @param string $connectionName
     *
     * @return ElasticSearchAdapter
     */
    public function getAdapter(string $connectionName): ElasticSearchAdapter
    {
        return $this->adapter->setConnection($connectionName);
    }

    /**
     * @param string $index
     *
     * @return string
     */
    public function getConnectionNameByIndex(string $index): string
    {
        return $this->connectionsByIndex[$index] ?? 'default';
    }

    /**
     * @param string $entityType
     *
     * @return string
     *
     * @throws Exception
     */
    protected function getWriteAliasByEntityType(string $entityType): string
    {
        $resourceData = $this->loadEntityResourceData($entityType);

        return $resourceData['write_alias'];
    }

    /**
     * @param string $entityType
     *
     * @return array
     *
     * @throws Exception
     */
    protected function getAllowedFieldsEntityType(string $entityType): array
    {
        $resourceData = $this->loadEntityResourceData($entityType);

        return $resourceData['allowed_fields'];
    }

    /**
     * @param string $entityType
     *
     * @return array
     * @throws Exception
     */
    protected function loadEntityResourceData(string $entityType): array
    {
        if (!empty($this->entityResourceData[$entityType])) {

            return $this->entityResourceData[$entityType];
        }

        $resourceModelClass = $this->coreHelper->getResourceModelClassNameByEntityType($entityType);
        if (!$resourceModelClass || !class_exists($resourceModelClass)) {
            throw new Exception('Entity "' . $entityType . '" is not defined (resource model does not exists)');
        }

        /** @var ElasticModel $resourceModel */
        $resourceModel = app()->make($resourceModelClass);
        $this->entityResourceData[$entityType] = [
            'write_alias'    => $resourceModel->getWriteAlias(),
            'allowed_fields' => $resourceModel->getAllowedFields()
        ];

        if (empty($this->connectionsByIndex[$resourceModel->getWriteAlias()])) {
            $this->connectionsByIndex[$resourceModel->getWriteAlias()] = $resourceModel->getConnectionName();
        }

        return $this->entityResourceData[$entityType];
    }

    /**
     * @param string $index
     * @param array  $data
     *
     * @return array
     */
    #[ArrayShape(['body' => "array[]", 'data' => "mixed"])]
    protected function buildCreateAction(string $index, array $data): array
    {
        $action = [
            'body' => [
                self::ES_ACTION_CREATE => [
                    '_index' => $index,
                    '_id'    => $data[AbstractModel::ID_FIELD_NAME]
                ]
            ],
        ];

        unset($data[AbstractModel::ID_FIELD_NAME]);
        if (empty($data['created_at'])) {
            $data['created_at'] = date(DateHelper::DATE_TIME_FORMAT);
        } else {
            $data['updated_at'] = date(DateHelper::DATE_TIME_FORMAT);
        }
        $action['data'] = $data;

        return $action;
    }

    /**
     * @param string $index
     * @param array  $data
     *
     * @return array
     */
    #[ArrayShape(['body' => "array[]", 'data' => "mixed"])]
    protected function buildUpdateAction(string $index, array $data): array
    {
        $action = [
            'body' => [
                self::ES_ACTION_UPDATE => [
                    '_index'            => $index,
                    '_id'               => $data[AbstractModel::ID_FIELD_NAME],
                    'retry_on_conflict' => ElasticSearch::RETRIES_COUNT_ON_CONFLICT
                ]
            ],
        ];
        unset($data[AbstractModel::ID_FIELD_NAME]);
        if (empty($data['updated_at'])) {
            $data['updated_at'] = date(DateHelper::DATE_TIME_FORMAT);
        }
        $action['data'] = ['doc' => $data];

        return $action;
    }

    /**
     * @param string $index
     * @param array  $data
     *
     * @return array
     */
    #[ArrayShape(['body' => "array[]"])]
    protected function buildDeleteAction(string $index, array $data): array
    {
        return [
            'body' => [
                self::ES_ACTION_DELETE => [
                    '_index' => $index,
                    '_id'    => $data[AbstractModel::ID_FIELD_NAME],
                ]
            ],
        ];
    }

    /**
     * @param string $entityType
     * @param array  $batch
     *
     * @return array
     * @throws Exception
     */
    protected function prepareBatch(string $entityType, array $batch): array
    {
        $this->validateBatch($batch);

        $allowedFields = $this->getAllowedFieldsEntityType($entityType);
        foreach ($batch as &$attributes) {
            $attributes = array_intersect_key($attributes, $allowedFields);
        }

        return $batch;
    }

    /**
     * @param array $batch
     *
     * @throws InvalidArgumentException
     */
    protected function validateBatch(array $batch)
    {
        if (empty($batch)) {
            throw new InvalidArgumentException('Required data was not specified');
        }
        $ids = array_column($batch, AbstractModel::ID_FIELD_NAME);
        if (empty($ids) || count($ids) != count($batch)) {
            throw new InvalidArgumentException('Cannot add bulk actions: some or all entity IDs do not exist in data batch');
        }
    }

    /**
     * @return int
     */
    public function getBulkRegistryCount(): int
    {
        return count($this->bulkRegistry);
    }
}
