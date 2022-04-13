<?php

namespace Levtechdev\Simpaas\Database\Elasticsearch;

use Elasticsearch\Common\Exceptions\Missing404Exception;
use Elasticsearch\Helper\Iterators\SearchResponseIterator;
use Elasticsearch\Common\Exceptions\NoNodesAvailableException;
use Exception;
use JetBrains\PhpStorm\ArrayShape;
use Levtechdev\Simpaas\Database\DbAdapterInterface;
use Levtechdev\Simpaas\Database\ElasticSearch\Client\BaseClient;
use Levtechdev\Simpaas\Database\SearchCriteria;
use Levtechdev\Simpaas\Helper\DateHelper;
use Levtechdev\Simpaas\Helper\Logger as LoggerHelper;
use Throwable;

class ElasticSearchAdapter implements DbAdapterInterface
{
    const LOG_CHANNEL = 'elasticsearch';

    const TYPE                         = '_doc';
    const DOCUMENT_ID_FIELD_NAME       = '_id';
    const SCROLL_DEFAULT_TTL           = '2m';
    const REFRESH_WAIT_FOR             = 'wait_for';
    const RETRIES_COUNT_ON_CONFLICT    = 3;
    const DEFAULT_MAX_TRACK_TOTAL_HITS = 10000;
    const NULL_VALUE                   = 'NULL';

    /** @var string */
    protected string $connection = 'default';

    /** @var BaseClient|null */
    protected BaseClient|null $readClient = null;

    /** @var BaseClient|null */
    protected BaseClient|null $writeClient = null;

    /** @var bool */
    protected bool $forcedWriteConnection = false;

    /** @var bool */
    protected bool $logAllQueries = false;

    protected \Monolog\Logger|null $logger = null;

    /**
     * ElasticSearch constructor.
     * @throws Exception
     */
    public function __construct()
    {
        $this->logAllQueries = env('ES_DB_QUERY_DEBUG', false);
        if ($this->logAllQueries) {
            /** @var LoggerHelper $logger */
            $logger = app(LoggerHelper::class);
            $this->logger = $logger->getLogger(
                self::LOG_CHANNEL,
                base_path(LoggerHelper::LOGS_DIR . DbAdapterInterface::DATABASE_QUERY_LOG_FILE)
            );
        }

//        $this->getReadClient();
//        $this->getWriteClient();
    }

    // =======================================
    // ============ Read Queries =============
    // =======================================

    /**
     * @param string $index
     *
     * @return array
     */
    protected function buildReadQuery(string $index): array
    {
        $query['index'] = $index;

        return $query;
    }

    /**
     * @param string $index
     * @param string $id
     * @param string $idFieldName
     * @param array  $excludedFields
     *
     * @return array
     */
    public function getDoc(string $index, string $id, string $idFieldName = self::DOCUMENT_ID_FIELD_NAME, array $excludedFields = []): array
    {
        $query = [
            'query' => [
                'bool' => [
                    'filter' => [
                        'term' => [$idFieldName => $id]
                    ]
                ]

            ]
        ];

        if (!empty($excludedFields)) {
            $query['_source']['exclude'] = $excludedFields;
        }

        $items = $this->search($index, $query);
        if (empty($items['hits']['hits'][0])) {

            return [];
        }

        foreach ($items['hits']['hits'] as $key => $value) {

            return $value;
        }

        return [];
    }

    /**
     * @param string $index
     * @param array $query
     *
     * @return array
     */
    public function getDocByQuery(string $index, array $query): array
    {
        $items = $this->search($index, $query);
        if (empty($items['hits']['hits'][0])) {

            return [];
        }

        foreach ($items['hits']['hits'] as $key => $value) {

            return $value;
        }

        return [];
    }

    /**
     * @param string $index
     * @param array  $filters
     * @param null   $trackTotalHits
     * @param array  $params
     *
     * @return array|callable
     */
    public function search(string $index, array $filters, $trackTotalHits = null, array $params = []): array|callable
    {
        $query = $this->buildSearchQuery($index, $filters, null, $trackTotalHits);
        $startT = microtime(true);

        if (!empty($params)) {
            $query = array_merge($query, $params);
        }

        $results = $this->getCurrentConnection()->search($query);
        $this->logQuery($query, microtime(true) - $startT);

        return $results;
    }

    /**
     * @param string $index
     * @param array  $queries
     * @param array  $params
     *
     * @return array
     */
    public function multiSearch(string $index, array $queries, array $params = []): array
    {
        $msearchBody = [];
        foreach ($queries as $queryBody) {
            if (!key_exists('size', $queryBody)) {
                $queryBody['size'] = SearchCriteria::MAX_PAGE_SIZE;
            }

            $msearchBody['body'][] = array_merge($params, ['index' => $index]);
            $msearchBody['body'][] = $queryBody;
        }

        if (empty($msearchBody)) {

            return [];
        }

        $startT = microtime(true);
        $results = $this->getCurrentConnection()->msearch($msearchBody);
        $this->logQuery($msearchBody, microtime(true) - $startT);

        if (empty($results['responses'])) {

            return [];
        }

        $finalResults = [];
        foreach ($results['responses'] as $response) {
            $finalResults[] = $response['status'] != 200 ? [] : $response;
        }

        return $finalResults;
    }

    /**
     * Initializes SearchResponseIterator with scroll parameters and returns it
     *
     * Note: it will override size and sort parameters inside $filters input
     *
     * @param string $index
     * @param array  $filters
     * @param int    $size
     * @param string $ttl
     *
     * @return SearchResponseIterator
     */
    public function scroll(
        string $index,
        array $filters = [],
        int $size = SearchCriteria::MAX_PAGE_SIZE,
        string $ttl = self::SCROLL_DEFAULT_TTL
    ): SearchResponseIterator {
        $filters['size'] = $size;

        return new SearchResponseIterator(
            $this->getCurrentConnection(),
            $this->buildSearchQuery($index, $filters, $ttl)
        );
    }

    /**
     * @param string $index
     * @param array $filters
     * @param int $size
     * @param string $ttl
     *
     * @return array|callable
     */
    public function scrollInit(
        string $index,
        array $filters = [],
        int $size = SearchCriteria::MAX_PAGE_SIZE,
        string $ttl = self::SCROLL_DEFAULT_TTL
    ): array|callable {
        $filters['size'] = $size;

        $query = $this->buildSearchQuery($index, $filters, $ttl);
        $startT = microtime(true);
        $results = $this->getCurrentConnection()->search($query);
        $this->logQuery($query, microtime(true) - $startT);

        return $results;
    }

    /**
     * @param string $scrollId
     * @param string $ttl
     *
     * @return array|callable
     */
    public function scrollNext(
        string $scrollId,
        string $ttl = self::SCROLL_DEFAULT_TTL
    ): array|callable {
        $query = [
            'scroll_id' => $scrollId,
            'scroll'    => $ttl
        ];
        $startT = microtime(true);
        $results = $this->getCurrentConnection()->scroll($query);
        $this->logQuery($query, microtime(true) - $startT);

        return $results;
    }

    /**
     * @param string        $index
     * @param array         $filters
     * @param string|null   $scroll
     * @param bool|int|null $trackTotalHits
     *
     * @return array
     */
    protected function buildSearchQuery(
        string $index,
        array $filters,
        ?string $scroll = null,
        bool $trackTotalHits = null
    ): array {
        $query = $this->buildReadQuery($index);

        if (!key_exists('size', $filters)) {
            $filters['size'] = SearchCriteria::MAX_PAGE_SIZE;
        }

        if (!empty($scroll)) {
            $query['scroll'] = $scroll;
            $query['sort'] = self::TYPE;
        }
        if ($trackTotalHits !== null) {
            $query['track_total_hits'] = $trackTotalHits;
        }

        $query['body'] = $filters;

        return $query;
    }

    /**
     * Fetch array of search results
     *
     * @param string $index
     * @param array  $filters
     *
     * @return array
     */
    public function fetchAll(string $index, array $filters = []): array
    {
        $results = [];
        $items = $this->search($index, $filters);

        if (empty($items['hits']['hits'][0])) {

            return $results;
        }

        foreach ($items['hits']['hits'] as $key => $value) {
            $data = $value['_source'];
            $data['id'] = $value['_id'];
            $results[] = $data;
        }

        return $results;
    }

    /**
     * Fetch array of IDs by search criteria
     *
     * @param string $index
     * @param array  $filters
     *
     * @return array
     */
    public function fetchIds(string $index, array $filters = []): array
    {
        $results = [];
        $filters['_source'] = false;
        $items = $this->search($index, $filters);

        if (empty($items['hits']['hits'][0])) {

            return $results;
        }

        foreach ($items['hits']['hits'] as $key => $value) {
            $results[] = $value['_id'];
        }

        return $results;
    }

    /**
     * Fetch array of search result pairs: first data element becomes a key and second becomes a value
     *
     * @param string $index
     * @param array  $pairFields
     * @param array  $filters
     *
     * @return array
     */
    public function fetchPairs(string $index, array $pairFields, array $filters = []): array
    {
        $results = [];
        $items = $this->search($index, $filters);
        if (empty($items['hits']['hits'][0])) {

            return $results;
        }

        $idField = in_array('id', $pairFields) || in_array(self::DOCUMENT_ID_FIELD_NAME, $pairFields);
        foreach ($items['hits']['hits'] as $key => $value) {
            $data = $value['_source'];
            if ($idField) {
                $data['id'] = $value['_id'];
            }
            $results[$data[$pairFields[0]]] = $data[$pairFields[1]];
        }

        return $results;
    }

    /**
     * @param string $index
     * @param array  $filters
     *
     * @return int
     */
    public function countDocs(string $index, array $filters = []): int
    {
        $query = $this->buildReadQuery($index);
        $query['body']['query'] = $filters['query'];

        $startT = microtime(true);
        $results = $this->getCurrentConnection()->count($query);
        $this->logQuery($query, microtime(true) - $startT);

        return $results['count'] ?? 0;
    }

    // =======================================
    // ============ Write Queries ============
    // =======================================

    /**
     * @param string $index
     * @param string $id
     *
     * @return array
     */
    protected function buildWriteQuery(string $index, string $id = ''): array
    {
        $query['index'] = $index;

        /**
         * Wait for the changes made by the request to be made visible by a refresh before replying
         * Needed so that immediate search can be performed on new documents
         */
        if ($this->forcedWriteConnection) {
            $query['refresh'] = self::REFRESH_WAIT_FOR;
        }

        if (!empty($id)) {
            $query['id'] = $id;
        }

        return $query;
    }

    /**
     * @param string $index
     * @param array  $data
     * @param array  $params
     * @param string $id
     *
     * @return array|callable
     */
    public function addDoc(string $index, array $data, array $params = [], string $id = ''): array|callable
    {
        $query = $this->buildWriteQuery($index, $id);
        $query['body'] = $data;
        if (!empty($params)) {
            $query = array_merge($query, $params);
        }

        $startT = microtime(true);
        $results = $this->getWriteClient()->index($query);
        unset($query['body']);
        $query['single_index'] = 1;
        $this->logQuery($query, microtime(true) - $startT);

        return $results;
    }

    /**
     * Important: it will not override multi dimensional fields but only update matching keys on document
     *            to override all fields, regardless of their contents
     *            use self::addDoc() as it will call PUT /index_name/_type/id API which will override everything
     *
     * @param string     $index
     * @param string     $id
     * @param array      $data
     *
     * @return array|callable
     */
    public function updateDoc(string $index, string $id, array $data): array|callable
    {
        $query = $this->buildWriteQuery($index, $id);
        $query['body']['doc'] = $data;
        $query['retry_on_conflict'] = self::RETRIES_COUNT_ON_CONFLICT;

        $startT = microtime(true);
        $results = $this->getWriteClient()->update($query);
        unset($query['body']);
        $query['single_update'] = 1;
        $this->logQuery($query, microtime(true) - $startT);

        return $results;
    }

    /**
     * @param string $index
     * @param string $id
     *
     * @return array|callable
     */
    public function deleteDoc(string $index, string $id): array|callable
    {
        $query = $this->buildWriteQuery($index, $id);

        $startT = microtime(true);
        $results = $this->getWriteClient()->delete($query);
        $query['single_delete'] = 1;
        $this->logQuery($query, microtime(true) - $startT);

        return $results;
    }

    /**
     * @param string $index
     * @param array $data
     * @return array|callable
     *
     * @throws NoNodesAvailableException
     */
    public function addDocs(string $index, array $data): array|callable
    {
        if (empty($data)) {
            throw new \InvalidArgumentException('Specified empty data when calling ' . __METHOD__ . '()');
        }

        /**
         * ?refresh=wait_for needed so that immediate search can be performed on new documents
         */
        if ($this->forcedWriteConnection) {
            $query['refresh'] = self::REFRESH_WAIT_FOR;
        }

        $query['body'] = [];
        foreach ($data as $key => $value) {
            $query['body'][] = [
                'index' => [
                    '_index' => $index,
                    '_id'    => $value['id']
                ]
            ];
            unset($data[$key]['id']);
            $query['body'][] = $data[$key];
        }

        $startT = microtime(true);
        $results = $this->getWriteClient()->bulk($query);

        if ($this->logAllQueries) {
            $query['bulk_index'] = ['count' => count($query['body']) / 2, 'data_size' => strlen(print_r($query, true))];
        }
        unset($query['body']);
        $this->logQuery($query, microtime(true) - $startT);

        return $results;
    }

    /**
     * @param string $index
     * @param array $data
     *
     * @return array
     *
     * @throws NoNodesAvailableException
     */
    public function updateDocs(string $index, array $data): array
    {
        if (empty($data)) {
            throw new \InvalidArgumentException('Specified empty data when calling ' . __METHOD__ . '()');
        }

        /**
         * ?refresh=wait_for needed so that immediate search can be performed on new documents
         */
        if ($this->forcedWriteConnection) {
            $query['refresh'] = self::REFRESH_WAIT_FOR;
        }

        $query['body'] = [];
        foreach ($data as $key => $value) {
            if (!key_exists('id', $value)) {
                continue;
            }
            unset($data[$key]['id']);
            $query['body'][] = [
                'update' => [
                    '_index'            => $index,
                    '_id'               => $value['id'],
                    'retry_on_conflict' => self::RETRIES_COUNT_ON_CONFLICT
                ],
            ];
            $query['body'][] = ['doc' => $data[$key]];
        }

        $startT = microtime(true);
        $results = $this->getWriteClient()->bulk($query);

        if ($this->logAllQueries) {
            $query['bulk_update'] = ['count' => count($query['body']) / 2, 'data_size' => strlen(print_r($query, true))];
        }
        unset($query['body']);
        $this->logQuery($query, microtime(true) - $startT);

        return $results;
    }

    /**
     * Bulk documents update by filter
     *
     * @param string $index
     * @param array  $filters
     * @param array  $updateData
     * @param array  $params
     *
     * @return array|callable
     */
    public function updateDocsByFilter(string $index, array $filters, array $updateData, array $params = []): array|callable
    {
        if (empty($updateData)) {
            throw new \InvalidArgumentException('Specified empty data when calling ' . __METHOD__ . '()');
        }

        $query = $this->buildWriteQuery($index);

        /**
         * https://www.elastic.co/guide/en/elasticsearch/reference/master/docs-update-by-query.html#_url_parameters_2
         */
        if ($this->forcedWriteConnection) {
            $query['refresh'] = 'true';
        }

        if (!empty($params['ignore_on_conflict'])) {
            $query['conflicts'] = 'proceed';
        }

        $script = $this->buildUpdateFieldsScript($updateData);
        $query['body']['query'] = $filters['query'];
        $query['body']['script'] = $script;

        $startT = microtime(true);
        $results = $this->getWriteClient()->updateByQuery($query);
        $this->logQuery($query, microtime(true) - $startT);

        return $results;
    }

    /**
     * Bulk filtering documents and update using raw script
     *
     * @param string $index
     * @param array  $filters
     * @param string $updateData
     * @param array  $params
     * @param array  $scriptParams
     *
     * @return array
     */
    public function updateDocsByRawScript(
        string $index, array $filters, string $updateData, array $params = [], array $scriptParams = []
    ): array|callable {
        if (empty($updateData)) {
            throw new \InvalidArgumentException('Specified empty data when calling ' . __METHOD__ . '()');
        }

        $query = $this->buildWriteQuery($index);

        /**
         * https://www.elastic.co/guide/en/elasticsearch/reference/master/docs-update-by-query.html#_url_parameters_2
         */
        if ($this->forcedWriteConnection) {
            $query['refresh'] = 'true';
        }

        if (!empty($params['ignore_on_conflict'])) {
            $query['conflicts'] = 'proceed';
        }

        $query['body']['query'] = $filters['query'];
        $query['body']['script'] = $this->buildScriptRawUpdate($updateData, $scriptParams);

        $startT = microtime(true);
        $results = $this->getWriteClient()->updateByQuery($query);
        $this->logQuery($query, microtime(true) - $startT);

        return $results;
    }

    /**
     * Bulk documents properties removal by filter
     *
     * @param string $index
     * @param array  $filters
     * @param array  $removePropertiesList
     * @param array  $params
     *
     * @return array|callable
     */
    public function removeDocsPropertiesByFilter(
        string $index, array $filters, array $removePropertiesList, array $params = []
    ): array|callable {
        if (empty($removePropertiesList)) {
            throw new \InvalidArgumentException('Specified empty data when calling ' . __METHOD__ . '()');
        }

        $query = $this->buildWriteQuery($index);

        /**
         * https://www.elastic.co/guide/en/elasticsearch/reference/master/docs-update-by-query.html#_url_parameters_2
         */
        if ($this->forcedWriteConnection) {
            $query['refresh'] = 'true';
        }

        if (!empty($params['ignore_on_conflict'])) {
            $query['conflicts'] = 'proceed';
        }

        $script = $this->buildRemoveFieldsScript($removePropertiesList);
        $query['body']['query'] = $filters['query'];
        $query['body']['script'] = $script;

        $startT = microtime(true);
        $results = $this->getWriteClient()->updateByQuery($query);
        $this->logQuery($query, microtime(true) - $startT);

        return $results;
    }

    /**
     * @param string $index
     * @param array  $filters
     *
     * @return array|callable
     */
    public function deleteDocsByFilter(string $index, array $filters): array|callable
    {
        $query = $this->buildWriteQuery($index);
        $query['body']['query'] = key_exists('query', $filters) ? $filters['query'] : $filters;

        /**
         * https://www.elastic.co/guide/en/elasticsearch/reference/current/docs-delete-by-query.html#_refreshing_shards
         */
        if ($this->forcedWriteConnection) {
            $query['refresh'] = 'true';
        }
        $query['conflicts'] = 'proceed';

        $startT = microtime(true);
        $results = $this->getWriteClient()->deleteByQuery($query);
        $this->logQuery($query, microtime(true) - $startT);

        return $results;
    }

    /**
     * @param string $index
     * @param string $id
     * @param array  $data
     *
     * @return array|callable
     */
    public function addDocFieldsByScript(string $index, string $id, array $data): array|callable
    {
        if (empty($data)) {
            throw new \InvalidArgumentException('Specified empty data when calling ' . __METHOD__ . '()');
        }
        $query = $this->buildWriteQuery($index, $id);
        $script = $this->buildAddFieldsScript($data);
        $query['body']['script'] = $script;
        $query['retry_on_conflict'] = self::RETRIES_COUNT_ON_CONFLICT;

        $startT = microtime(true);
        $results = $this->getWriteClient()->update($query);
        $this->logQuery($query, microtime(true) - $startT);

        return $results;
    }

    /**
     * @param string $index
     * @param array  $filters
     * @param array  $updateData
     * @param array  $params
     *
     * @return array|callable
     */
    public function addDocFieldsByFilter(
        string $index,
        array $filters,
        array $updateData,
        array $params = []
    ): array|callable {
        if (empty($updateData)) {
            throw new \InvalidArgumentException('Specified empty data when calling ' . __METHOD__ . '()');
        }

        $query = $this->buildWriteQuery($index);

        if (!empty($params['ignore_on_conflict'])) {
            $query['conflicts'] = 'proceed';
        }

        /** Async: https://www.elastic.co/guide/en/elasticsearch/client/php-api/current/future_mode.html */
        if (!empty($params['future'])) {
            $query['client']['future'] = 'lazy';
        }

        $script = $this->massBuildAddFieldsScript($updateData);
        $query['body']['query'] = $filters['query'];
        $query['body']['script'] = $script;

        $startT = microtime(true);
        $results = $this->getWriteClient()->updateByQuery($query);
        $this->logQuery($query, microtime(true) - $startT);

        return $results;
    }

    /**
     * @param string $index
     * @param string $id
     * @param array  $data
     *
     * @return array|callable
     */
    public function updateDocFieldsByScript(string $index, string $id, array $data): array|callable
    {
        if (empty($data)) {
            throw new \InvalidArgumentException('Specified empty data when calling ' . __METHOD__ . '()');
        }
        $query = $this->buildWriteQuery($index, $id);
        $script = $this->buildUpdateFieldsScript($data);
        $query['body']['script'] = $script;
        $query['retry_on_conflict'] = self::RETRIES_COUNT_ON_CONFLICT;

        $startT = microtime(true);
        $results = $this->getWriteClient()->update($query);
        $this->logQuery($query, microtime(true) - $startT);

        return $results;
    }

    /**
     * @param string $index
     * @param string $id
     * @param string $source
     * @param array $params
     *
     * @return array|callable
     */
    public function updateDocByScript(string $index, string $id, string $source, array $params = []): array|callable
    {
        if (empty($source)) {
            throw new \InvalidArgumentException('Specified empty script body when calling ' . __METHOD__ . '()');
        }
        $query = $this->buildWriteQuery($index, $id);

        $script = [
            'source' => $source,
        ];
        if (!empty($params)) {
            $script['params'] = $params;
        }
        $query['body']['script'] = $script;
        $query['retry_on_conflict'] = self::RETRIES_COUNT_ON_CONFLICT;

        $startT = microtime(true);
        $results = $this->getWriteClient()->update($query);
        $this->logQuery($query, microtime(true) - $startT);

        return $results;
    }

    /**
     * @param string $index
     * @param string $id
     * @param array  $data
     *
     * @return array|callable
     */
    public function deleteDocFieldsByScript(string $index, string $id, array $data): array|callable
    {
        if (empty($data)) {
            throw new \InvalidArgumentException('Specified empty data when calling ' . __METHOD__ . '()');
        }
        $query = $this->buildWriteQuery($index, $id);
        $script = $this->buildRemoveDataScript($data);
        $query['body']['script'] = $script;
        $query['retry_on_conflict'] = self::RETRIES_COUNT_ON_CONFLICT;

        $startT = microtime(true);
        $results = $this->getWriteClient()->update($query);
        $this->logQuery($query, microtime(true) - $startT);

        return $results;
    }

    /**
     * @param array $updateData
     *
     * @return array
     */
    protected function buildAddFieldsScript(array $updateData): array
    {
        return [
            'lang'   => 'painless',
            'inline' => "ctx._source.updated_at = '" . date(DateHelper::DATE_TIME_FORMAT) . "';" .
                        $this->flattenCtxAddData($updateData)
        ];
    }

    /**
     * Support only array list fields
     *
     * @param array $updateData
     *
     * @return array
     */
    protected function massBuildAddFieldsScript(array $updateData): array
    {
        $script['lang'] = 'painless';

        $dynamicFieldPattern = 'field_%d';
        $painlessRawScript = /** @lang painless */<<<PAINLESS
            try {
                if (!ctx._source.%s.contains(params.%s)) {
                    ctx._source.%s.add(params.%s);
                }
            } catch(Exception e) {
                ctx._source.%s = new ArrayList();
                ctx._source.%s.add(params.%s);
            }
        PAINLESS;

        $source = '';
        $params = [];
        $i = 0;

        foreach ($updateData as $field => $value) {
            $i++;
            $dynamicField = sprintf($dynamicFieldPattern, $i);
            $source .= sprintf(
                $painlessRawScript,
                $field, $dynamicField,
                $field, $dynamicField,
                $field,
                $field, $dynamicField
            );
            $params[$dynamicField] = $value;
        }

        $script['source'] = "ctx._source.updated_at = '" . date(DateHelper::DATE_TIME_FORMAT) . "';
            " . $source; // new line required


        if (!empty($params)) {
            $script['params'] = $params;
        }

        return $script;
    }

    /**
     * @param array $updateData
     *
     * @return array
     */
    #[ArrayShape(['lang' => "string", 'inline' => "string"])]
    protected function buildUpdateFieldsScript(array $updateData): array
    {
        return [
            'lang'   => 'painless',
            'inline' => "ctx._source.updated_at = '" . date(DateHelper::DATE_TIME_FORMAT) . "';" .
                        $this->flattenCtxUpdateData($updateData)
        ];
    }

    /**
     * @param string $script
     * @param array  $params
     *
     * @return array
     */
    #[ArrayShape(['lang' => "string", 'source' => "string", 'params' => "array"])]
    protected function buildScriptRawUpdate(string $script, array $params = []): array
    {
        $script = [
            'lang'   => 'painless',
            'source' =>
                'ctx._source.updated_at = "' . date('Y-m-d H:i:s') . '";
                ' . $script // new line required
        ];

        if (!empty($params)) {
            $script['params'] = $params;
        }

        return $script;
    }

    /**
     * @param array $updateData
     *
     * @return array
     */
    #[ArrayShape(['lang' => "string", 'inline' => "string"])]
    protected function buildRemoveDataScript(array $updateData): array
    {
        return [
            'lang'   => 'painless',
            'inline' => "ctx._source.updated_at = '" . date(DateHelper::DATE_TIME_FORMAT) . "';" .
                        $this->flattenCtxRemoveData($updateData)
        ];
    }

    /**
     * @param array $removePropertiesList
     *
     * @return array
     */
    #[ArrayShape(['lang' => "string", 'inline' => "string"])]
    protected function buildRemoveFieldsScript(array $removePropertiesList): array
    {
        return [
            'lang'   => 'painless',
            'inline' => $this->flattenCtxRemoveFields($removePropertiesList)
        ];
    }

    /**
     * @todo For most contexts, you can compile up to 75 scripts per 5 minutes by default.
     * @link https://www.elastic.co/guide/en/elasticsearch/reference/7.x/modules-scripting-using.html
     *
     * @param array  $fields
     * @param string $prefix
     *
     * @return string
     */
    protected function flattenCtxAddData(array $fields, string $prefix = ''): string
    {
        $inlineString = '';
        foreach ($fields as $key => $value) {
            if (is_array($value)) {
                $inlineString .= $this->flattenCtxAddData($value, $prefix . $key . '.');
            } else {
                $value = (is_string($value)) ? "'" . $value . "'" : $value;
                $inlineString .= sprintf(
                    "try {if(!ctx._source.%s.contains(%s)){ctx._source.%s.add(%s);}} catch(Exception e){ ctx._source.%s = new ArrayList(); ctx._source.%s.add(%s);}",
                    $prefix . $key,
                    $value,
                    $prefix . $key,
                    $value,
                    $prefix . $key,
                    $prefix . $key,
                    $value
                );
            }
        }

        return $inlineString;
    }

    /**
     * @param array  $updateData
     * @param string $prefix
     *
     * @return string
     */
    protected function flattenCtxUpdateData(array $updateData, string $prefix = ''): string
    {
        $inlineString = '';
        $startLine = 'ctx._source.';
        foreach ($updateData as $key => $value) {
            if (is_array($value)) {
                $inlineString .= $this->flattenCtxUpdateData($value, $prefix . $key . '.');
            } else {
                $value = (is_string($value)) ? "'" . $value . "'" : $value;
                $inlineString .= $startLine . $prefix . $key . " = " . $value . ";";
            }
        }

        return $inlineString;
    }

    /**
     * @param array  $fields
     * @param string $prefix
     *
     * @return string
     */
    protected function flattenCtxRemoveData(array $fields, string $prefix = ''): string
    {
        $inlineString = '';
        foreach ($fields as $key => $value) {
            if (is_array($value)) {
                $inlineString .= $this->flattenCtxRemoveData($value, $prefix . $key . '.');
            } else {
                $value = (is_string($value)) ? "'" . $value . "'" : $value;
                $inlineString .= sprintf(
                    "try {if(ctx._source.%s.contains(%s)){ctx._source.%s.remove(ctx._source.%s.indexOf(%s))}} catch(Exception e){}",
                    $prefix . $key,
                    $value,
                    $prefix . $key,
                    $prefix . $key,
                    $value
                );
            }
        }

        return $inlineString;
    }

    /**
     * @param array $fields
     *
     * @return string
     */
    protected function flattenCtxRemoveFields(array $fields): string
    {
        $inlineString = '';
        foreach ($fields as $fieldName) {
            $fieldNameParts = explode('.', $fieldName);
            $removalField = array_shift($fieldNameParts);

            $inlineString .= sprintf(
                "try {ctx._source.%sremove('%s')} catch(Exception e){}",
                !empty($fieldNameParts) ? implode('.', $fieldNameParts) . '.' : '',
                $removalField
            );
        }

        return $inlineString;
    }

    // =======================================
    // ============ Index Queries ============
    // =======================================

    /**
     * @param string $index
     *
     * @return bool
     */
    public function isIndexExist(string $index): bool
    {
        $query = $this->buildReadQuery($index);

        return $this->getCurrentConnection()->indices()->exists($query);
    }

    /**
     * @param string $indexName
     * @param array  $indexParams
     * @param string|null $writeAlias
     * @param string|null $readAlias
     *
     * @return array
     * @throws Exception
     */
    public function createIndex(
        string $indexName, array $indexParams, ?string $writeAlias = null, ?string $readAlias = null
    ): array {
        if ($this->isIndexExist($indexName)) {
            throw new Exception('Index "' . $indexName . '" already exists');
        }

        $mappingIndex = ['properties' => $indexParams['mapping']];
        if (!empty($indexParams['dynamic_templates'])) {
            $mappingIndex['dynamic_templates'] = $indexParams['dynamic_templates'];
        }
//        $mappingIndex['dynamic'] = false;

        $query = $this->buildReadQuery($indexName);
        $query['wait_for_active_shards'] = 'all';
        $query['body'] = [
            'settings' => $indexParams['settings'],
            'mappings' => $mappingIndex
        ];

        // @todo it is possible to specify aliases during index creation - https://www.elastic.co/guide/en/elasticsearch/reference/6.2/indices-create-index.html#create-index-aliases
        $result = $this->getWriteClient()->indices()->create($query);
        if (!$result['acknowledged']) {
            throw new Exception('Index "' . $indexName . '" cannot be created now');
        }

        if ($writeAlias && $readAlias) {
            $this->addWriteReadAliases($indexName, $writeAlias, $readAlias);
        }

        return $result;
    }

    /**
     * @param string $index
     * @param array $mapping
     *
     * @return array
     */
    public function updateIndexMapping(string $index, array $mapping): array
    {
        $mappingIndex = [
            'properties' => $mapping['mapping'],
            '_source'    => [
                'enabled' => true
            ],
        ];

        if (!empty($mapping['dynamic_templates'])) {
            $mappingIndex['dynamic_templates'] = $mapping['dynamic_templates'];
        }

        $query = $this->buildReadQuery($index);
        $query['body'] = $mappingIndex;

        return $this->getWriteClient()->indices()->putMapping($query);
    }

    /**
     * @param string $index
     * @param array $settings
     *
     * @return array
     */
    public function updateIndexSettings(string $index, array $settings): array
    {
        $query = $this->buildReadQuery($index);
        $query['body'] = $settings;

        return $this->getWriteClient()->indices()->putSettings($query);
    }

    /**
     * @param string $index
     *
     * @return array
     */
    public function deleteIndex(string $index): array
    {
        $query = $this->buildReadQuery($index);

        return $this->getWriteClient()->indices()->delete($query);
    }

    /**
     * @param string $name
     *
     * @return array
     */
    public function getAliasesByName(string $name): array
    {
        $results = [];
        try {
            $existingAliases = $this->getWriteClient()->indices()->getAlias(['name' => $name]);
            foreach ($existingAliases as $indexName => $aliases) {
                foreach ($aliases['aliases'] as $alias => $array) {
                    $results[$indexName][] = $alias;
                }
            }
        } catch (Missing404Exception $e) {

        }

        return $results;
    }

    /**
     * Reindex data for specified index
     * Does not implement zero downtime approach. Thus this function must be executed only when no writes are invoked
     * See self::reindexByETLCallback() for a zero downtime solution
     *
     * Step 1: creates a new index with the current timestamp
     * Step 2: re-point resource_write alias to the new index, remove old alias pointer - use $this->repointAlias()
     *   Note: now there will be some discrepancy in search results and real data coz new writes will go to the new
     *   index to prevent this - we can and should pause all data writes for a moment of reindexation - that will allow
     *   to make sure that we will not accidentally create partial documents and lose parent to child ID based
     *   relations when updating documents technically saying we should not reindex often and if we do - we should
     *   pause any writes
     * Step 3: Copy (reindex data) via _reindex ES API
     * Step 4: After reindexing all data, re-point read alias (resource_read) to the newly created index
     * Step 5: Remove the old index - not invoked for now to allow data restoration
     *
     * @see https://engineering.carsguide.com.au/elasticsearch-zero-downtime-reindexing-e3a53000f0ac
     *
     * @param string $from before any re-indexations the $from index must exist
     * @param string $to
     * @param array  $indexParams
     * @param string $readAlias
     * @param string $writeAlias
     *
     * @return bool
     * @throws Throwable
     */
    public function reindex(string $from, string $to, array $indexParams, string $readAlias, string $writeAlias): bool
    {
        $this->createIndex($to, $indexParams);
        $this->repointWriteAlias($from, $to, $writeAlias);

        try {
            $this->copyData($from, $to, $indexParams['reindex_slices'] ?? null);
        } catch (Throwable $e) {
            try {
                $this->repointWriteAlias($to, $from, $writeAlias); // try to restore last index alias
            } catch (Throwable $e) {

                throw $e;
            }
            throw $e;
        }

        try {
            $this->repointReadAlias($from, $to, $readAlias);

            return true;
        } catch (Throwable $e) {
            try {
                $this->repointWriteAlias($to, $from, $writeAlias); // try to restore last index alias
            } catch (Throwable $e) {
                throw $e;
            }
            throw $e;
        }
    }

    /**
     * @param array  $existingAliases
     * @param string $to
     * @param array  $indexParams
     * @param string $readAlias
     * @param string $writeAlias
     *
     * @return bool
     * @throws Exception
     */
    public function reindexBare(
        array $existingAliases, string $to, array $indexParams, string $readAlias, string $writeAlias
    ): bool {
        $this->createIndex($to, $indexParams);
        $this->repointWriteReadAliases($existingAliases, $to, $writeAlias, $readAlias);

        return true;
    }

    /**
     * @param string $oldIndexName
     * @param string $newIndex
     * @param string $alias
     *
     * @return array
     */
    public function repointWriteAlias(string $oldIndexName, string $newIndex, string $alias): array
    {
        $params['body'] = [
            'actions' => [
                [
                    'remove' => [
                        'alias' => $alias,
                        'index' => $oldIndexName,
                    ]
                ],
                [
                    'add' => [
                        'alias'          => $alias,
                        'index'          => $newIndex,
                        'is_write_index' => true,
                    ]
                ],
            ]
        ];

        return $this->getWriteClient()->indices()->updateAliases($params);
    }

    /**
     * @param string $oldIndexName
     * @param string $newIndex
     * @param string $alias
     *
     * @return array
     */
    public function repointReadAlias(string $oldIndexName, string $newIndex, string $alias): array
    {
        $params['body'] = [
            'actions' => [
                [
                    'remove' => [
                        'alias' => $alias,
                        'index' => $oldIndexName,
                    ]
                ],
                [
                    'add' => [
                        'alias' => $alias,
                        'index' => $newIndex,
                    ]
                ],
            ]
        ];

        return $this->getWriteClient()->indices()->updateAliases($params);
    }

    /**
     * @param array  $existingAliases
     * @param string $newIndex
     * @param string $writeAlias
     * @param string $readAlias
     *
     * @return array
     */
    public function repointWriteReadAliases(
        array $existingAliases,
        string $newIndex,
        string $writeAlias,
        string $readAlias
    ): array {
        $actions = [];
        foreach ($existingAliases as $oldIndexName => $aliases) {
            $actions[] = [
                'remove' => [
                    'aliases' => $aliases,
                    'index'   => $oldIndexName,
                ]
            ];
        }
        $actions[] = [
            'add' => [
                'alias'          => $writeAlias,
                'index'          => $newIndex,
                'is_write_index' => true,
            ]
        ];
        $actions[] = [
            'add' => [
                'alias' => $readAlias,
                'index' => $newIndex,
            ]
        ];

        $params['body'] = ['actions' => $actions];

        return $this->getWriteClient()->indices()->updateAliases($params);
    }

    /**
     * @param string $index
     * @param string $alias
     *
     * @return array
     * @throws Exception
     */
    public function addAlias(string $index, string $alias): array
    {
        $params = [
            'index' => $index,
            'name'  => $alias
        ];

        return $this->getWriteClient()->indices()->putAlias($params);
    }

    /**
     * @param string $index
     * @param array  $aliases
     *
     * @return array
     * @throws Exception
     */
    public function addAliases(string $index, array $aliases): array
    {
        $params['body'] = [
            'actions' => [
                [
                    'add' => [
                        'index'   => $index,
                        'aliases' => $aliases,
                    ]
                ],
            ]
        ];

        return $this->getWriteClient()->indices()->updateAliases($params);
    }

    /**
     * @param string $index
     * @param string $writeAlias
     * @param string $readAlias
     *
     * @return array
     * @throws Exception
     */
    public function addWriteReadAliases(string $index, string $writeAlias, string $readAlias): array
    {
        $params['body'] = [
            'actions' => [
                [
                    'add' => [
                        'index'          => $index,
                        'alias'          => $writeAlias,
                        'is_write_index' => true,
                    ]
                ],
                [
                    'add' => [
                        'index' => $index,
                        'alias' => $readAlias,
                    ]
                ],
            ]
        ];

        return $this->getWriteClient()->indices()->updateAliases($params);
    }

    /**
     * @param string $index
     * @param array $aliases
     *
     * @return array
     */
    public function removeAliases(string $index, array $aliases): array
    {
        $params['body'] = [
            'actions' => [
                [
                    'remove' => [
                        'index'   => $index,
                        'aliases' => $aliases,
                    ]
                ],
            ]
        ];

        return $this->getWriteClient()->indices()->updateAliases($params);
    }

    /**
     * @param string $targetIndex
     * @param string $sourceIndex
     *
     * @return array
     */
    public function cloneIndex(string $targetIndex, string $sourceIndex): array
    {
        $params = [
            'index'  => $sourceIndex,
            'target' => $targetIndex,
        ];

        return $this->getWriteClient()->indices()->clone($params);
    }

    /**
     * @param string   $oldIndex
     * @param string   $newIndex
     * @param null|int $reindexSlices
     *
     * @return array|callable
     */
    protected function copyData(string $oldIndex, string $newIndex, ?int $reindexSlices = null): array|callable
    {
        $params = [
            // If true, Elasticsearch refreshes the affected shards to make this operation visible to search
            'refresh' => true,
            'body'    => [
                // continue reindexing even if there are conflicts (on duplicate ignore logic) - needed when restarting indexation
                'conflicts' => 'proceed',
                'source'    => [
                    'index' => $oldIndex
                ],
                'dest'      => [
                    'index' => $newIndex
                ]
            ]
        ];

        // Query performance is most efficient when the number of slices is equal to the number of shards in the index
        if ($reindexSlices > 0) {
            $params['slices'] = (int)$reindexSlices;
        }

        return $this->getWriteClient()->reindex($params);
    }

    /**
     * @param string $indexName
     *
     * @return array
     * @throws Exception
     */
    public function forceMergeIndex(string $indexName): array
    {
        if (!$this->isIndexExist($indexName)) {
            throw new Exception('Index "' . $indexName . '" does not exist');
        }

        $query = $this->buildWriteQuery($indexName);
        $query['max_num_segments'] = 1;

        return $this->getWriteClient()->indices()->forcemerge($query);
    }

    // =======================================
    // =========== Client Methods ============
    // =======================================

    /**
     * @return array|callable array
     */
    public function info(): array|callable
    {
        return $this->getCurrentConnection()->info();
    }

    /**
     * @param array $params
     *
     * @return array
     */
    public function clusterHealth(array $params = []): array
    {
       return $this->getWriteClient()->cluster()->health($params);
    }
    /**
     * @return BaseClient
     */
    public function getReadClient(): BaseClient
    {
        if (empty($this->readClient[$this->connection])) {
            /** @var BaseClient $client */
            $this->readClient[$this->connection] = app(sprintf('elasticsearch.%s.read_client', $this->connection));
        }

        return $this->readClient[$this->connection];
    }

    /**
     * @return BaseClient
     */
    public function getWriteClient(): BaseClient
    {
        if (empty($this->writeClient[$this->connection])) {
            /** @var BaseClient $client */
            $this->writeClient[$this->connection] = app(sprintf('elasticsearch.%s.write_client', $this->connection));
        }

        return $this->writeClient[$this->connection];
    }

    /**
     * @param string $connection
     * @return $this
     */
    public function setConnection(string $connection = 'default'): self
    {
        $this->connection = $connection;

        return $this;
    }

    public function getConnection(): string
    {
        return $this->connection;
    }

    /**
     * @param \Closure $callback
     * @param array    $args
     *
     * @return mixed
     */
    public function enforceWriteConnection(\Closure $callback, array $args = []): mixed
    {
        $oldForcedFlag = $this->forcedWriteConnection;
        $this->forcedWriteConnection = true;
        $result = call_user_func($callback, $args);
        $this->forcedWriteConnection = $oldForcedFlag;

        return $result;
    }

    /**
     * @return bool
     */
    public function getCurrentEnforcedWriteMode(): bool
    {
        return $this->forcedWriteConnection;
    }

    /**
     * @return BaseClient
     */
    public function getCurrentConnection(): BaseClient
    {
        $connection = $this->readClient[$this->connection] ?? $this->getReadClient();
        if ($this->forcedWriteConnection) {
            $connection = $this->writeClient[$this->connection] ?? $this->getWriteClient();
        }

        return $connection;
    }

    /**
     * @param array $query
     * @param float|int $time
     *
     * @return $this
     */
    public function logQuery(array $query, float|int $time): self
    {
        if (extension_loaded('newrelic')) {
            newrelic_custom_metric('ElasticSearchDB', (float)($time * 1000));
        }

        if (!$this->logAllQueries) {

            return $this;
        }

        try {
            $e = new Exception();
            $context = [
                'query_time' => $time,
                'query'      => json_encode($query),
                'file'       => $e->getFile() . ':' . $e->getLine(),
                'trace'      => implode('#', array_slice(explode('#', $e->getTraceAsString()), 2, 10))
            ];

            $this->logger->debug('ES Query', $context);
        } catch (Throwable $e) {
            // ignore
        }

        return $this;
    }
}