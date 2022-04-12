<?php

namespace Levtechdev\Simpaas\Collection\Elasticsearch;

use JetBrains\PhpStorm\ArrayShape;
use Levtechdev\Simpaas\Collection\AbstractCollection;
use Levtechdev\Simpaas\Database\DbAdapterInterface;
use Levtechdev\Simpaas\Database\ElasticSearch\Builder\QueryBuilder;
use Levtechdev\Simpaas\Database\Elasticsearch\ElasticSearchAdapter;
use Levtechdev\Simpaas\Database\SearchCriteria;
use Levtechdev\Simpaas\Exceptions\EmptyCollectionException;
use Levtechdev\Simpaas\Exceptions\EntityFieldNotUniqueException;
use Levtechdev\Simpaas\Exceptions\FilterNotSpecifiedException;
use Levtechdev\Simpaas\Exceptions\NoDataChangesException;
use Levtechdev\Simpaas\Model\AbstractModel;
use Levtechdev\Simpaas\Model\Elasticsearch\AbstractElasticsearchModel;
use ONGR\ElasticsearchDSL\Query\Compound\BoolQuery;

class AbstractElasticsearchCollection extends AbstractCollection
{
    const TERMS_AGGREGATION_BUCKETS_LIMIT = 50;

    /** @var array  */
    protected array $orderByScript        = [];
    /** @var array  */
    protected array $aggregation          = [];
    /** @var array  */
    protected array $fulltextFilters      = [];
    /** @var array  */
    protected array $aggregationsToSelect = [];
    /** @var array  */
    protected array $collapse             = [];
    /** @var array  */
    protected array $rawSearchResults     = [];
    /** @var array  */
    protected array $postFilterGroups     = [];
    /** @var string  */
    protected string $filterLogic          = BoolQuery::FILTER;
    /** @var string|null  */
    protected string|null $preference           = null;
    /** @var array  */
    protected array $fieldsToExclude      = [];
    /** @var array  */
    protected array $nestedSort = [];
    /** @var string|null  */
    protected string|null $readIndex = null;

    /**
     * @var array aggregations sizes [term:field1 => 20]
     */
    protected array $aggregationsBucketsLimits = [];

    /**
     * @var array
     *    For product collection example [
     *      'price'             => [QueryBuilder::AGGREGATION_HISTOGRAM => ['price.final_price' => QueryBuilder::AGGREGATION_HISTOGRAM_DEFAULT_INTERVAL]],
     *       'price.final_price' => [QueryBuilder::AGGREGATION_HISTOGRAM => ['price.final_price' => QueryBuilder::AGGREGATION_HISTOGRAM_DEFAULT_INTERVAL]]
     * ]
     */
    protected array $mappedAggregations = [];

    public function __construct(AbstractElasticsearchModel $model, array $items = [], string $indexField = AbstractModel::ID_FIELD_NAME)
    {
        parent::__construct($model, $items, $indexField);
    }

    /**
     * @return DbAdapterInterface|ElasticSearchAdapter
     */
    public function getAdapter(): DbAdapterInterface|ElasticSearchAdapter
    {
        return $this->getModel()->getResource()->getAdapter();
    }

    /**
     * @return array
     */
    protected function getAggregationBucketsLimits(): array
    {
        return $this->aggregationsBucketsLimits;
    }

    /**
     * @param string|null $ref
     * @return $this
     */
    public function setPreference(?string $ref): self
    {
        if (!empty($ref)) {
            $this->preference = $ref;
        }

        return $this;
    }

    /**
     * @param array $searchCriteria
     *
     * @return $this
     */
    public function setSearchCriteria(array $searchCriteria): static
    {
        parent::setSearchCriteria($searchCriteria);

        if (isset($searchCriteria[SearchCriteria::PREFERENCE])) {
            $this->preference = $searchCriteria[SearchCriteria::PREFERENCE];
        }

        if (isset($searchCriteria[QueryBuilder::FULL_TEXT])) {
            $this->fulltextFilters = [$searchCriteria[QueryBuilder::FULL_TEXT]];
        }

        if (isset($searchCriteria[QueryBuilder::AGGREGATION])) {
            $this->addAggregationToSelect($searchCriteria[QueryBuilder::AGGREGATION]);
        }

        if (isset($searchCriteria[QueryBuilder::POST_FILTER])) {
            foreach ($searchCriteria[QueryBuilder::POST_FILTER] as $group) {
                $this->postFilterGroups[] = $group;
            }
        }

        if (isset($searchCriteria[QueryBuilder::SORT_BY_SCRIPT])) {
            foreach ($searchCriteria[QueryBuilder::SORT_BY_SCRIPT] as $sort) {
                $this->orderByScript($sort);
            }
        }

        if (isset($searchCriteria[QueryBuilder::EXCLUDE_FIELDS])) {
            $this->excludeFieldFromSelect($searchCriteria[QueryBuilder::EXCLUDE_FIELDS]);
        }

        return $this;
    }

    /**
     * @param array $filterGroups
     *
     * @return $this
     */
    public function setSearchCriteriaPostFilters(array $filterGroups): static
    {
        if (empty($filterGroups)) {

            return $this;
        }

        foreach ($filterGroups as $group) {
            $this->postFilterGroups[] = $group;
        }

        return $this;
    }

    /**
     * @param string $fieldName
     * @param string $order
     * @param string $mode
     *
     * @return $this
     */
    public function addNestedOrder(string $fieldName, string $order = SearchCriteria::SORT_ORDER_DESC, string $mode = 'max'): static
    {
        $this->nestedSort[$fieldName] = [
            'field' => $fieldName,
            'order' => $order,
            'mode'  => $mode
        ];

        return $this;
    }

    /**
     * @param array $sort
     *
     * @return $this
     */
    public function orderByScript(array $sort): static
    {
        $this->orderByScript[] = $sort;

        return $this;
    }

    /**
     * @param array  $fields
     * @param string $text
     * @param string $searchType
     * @param array  $parameters
     *
     * @return $this
     */
    public function addFieldsToSearch(array $fields, string $text, string $searchType = QueryBuilder::MUST, array $parameters = []): static
    {
        $this->fulltextFilters[] = [
            'fields'     => $fields,
            'value'      => $text,
            'match_type' => $searchType,
            'parameters' => $parameters,
        ];

        return $this;
    }

    /**
     * Add typed/not typed aggregation(s) or field(s)
     *
     * $field Examples:
     * $field = 'field1'
     * $field = ['field1', 'field2']
     * $field = ['metric_type' => ['field1', 'field2'], 'metric_type' => ['field3', 'field4']]
     * $field = ['metric_type' => ['alias1' => 'field1', 'alias2' => 'field2'], 'metric_type' => ['field3']]
     * $field = ['histogram' => ['field1' => interval, 'field2', 'field3']
     * $field = ['composite' => [['fields' => 'field1', 'after_key' => ['field1' => "lastAggValue"], "size" => int]]]
     *
     * @param array|string|int $field
     * @param int|null         $aggregationsSize on $field as string OR fields list, for others cases use composite
     *                                       aggregation
     *
     * @return void
     *
     * @todo crazy method but optimal - must be simplified
     */
    public function addAggregationToSelect(array|string|int $field, ?int $aggregationsSize = null): void
    {
        if ($aggregationsSize === null) {
            $aggregationsSize = static::TERMS_AGGREGATION_BUCKETS_LIMIT;
        }

        // If mapped aggregation by alias exists - use it
        if (!is_array($field) && key_exists($field, $this->mappedAggregations)) {
            $field = $this->mappedAggregations[$field];
        }
        if (is_array($field)) {
            foreach ($field as $key => $value) {
                // Multi field, typed aggregation case: $field = ['max' => ['field1']] OR $field = ['max' => ['alias' => 'field1']] OR $field = ['histogram' => ['field1' => interval]]
                if (is_array($value)) {
                    if (!key_exists($key, $this->aggregationsToSelect)) {
                        $this->aggregationsToSelect[$key] = $value;
                    } else {
                        $this->aggregationsToSelect[$key] = array_merge($this->aggregationsToSelect[$key], $value);
                    }
                } else {
                    // Multi field case: $field = ['field1', 'field2']
                    if (is_numeric($key) && !in_array($value, $this->aggregationsToSelect)) {
                        // If mapped aggregation by alias exists - use it
                        if (key_exists($value, $this->mappedAggregations)) {
                            $this->addAggregationToSelect($this->mappedAggregations[$value]);
                        } else {
                            $this->aggregationsToSelect[] = $value;
                            $this->aggregationsBucketsLimits[$value] = $aggregationsSize;
                        }
                    } // Single field, typed aggregation case: $field = ['max' => 'field1']
                    else if (empty($this->aggregationsToSelect[$key])
                        || !in_array($value, array_values($this->aggregationsToSelect))
                    ) {
                        if (!in_array($value, $this->aggregationsToSelect)) {
                            $this->aggregationsToSelect[$key][] = $value;
                        }
                    }
                }
            }
            // Single field, not typed aggregation case: $field = 'field1'
        } else if (!in_array($field, $this->aggregationsToSelect)) {
            $this->aggregationsToSelect[] = $field;
            $this->aggregationsBucketsLimits[$field] = $aggregationsSize;
        }
    }

    /**
     * @param string[]|string $field
     *
     * @return $this
     */
    public function excludeFieldFromSelect(array|string $field): static
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
     * Group/collapse products by common field
     *
     * IMPORTANT: group by ES field MUST be declared as multi-field via ES mapping and have multi-field named "hash"
     * with type "murmur3"
     *
     * In QueryBuilder now, when collapsing by field:
     *   1. The aggregations are now enforced with cardinality sub aggregations to properly count aggregation buckets
     *   2. First level cardinality count aggregation added to properly count collapsed documents
     *
     * @param string $field
     * @param array  $innerHits
     *
     * @return $this
     */
    public function groupBy(string $field, array $innerHits): static
    {
        $this->collapse = [
            'field'      => $field,
            'inner_hits' => $innerHits,
            //'max_concurrent_group_searches' => 4 // @todo research for optimization
        ];

        return $this;
    }

    /**
     * Assemble search criteria and convert it to ES query via DSL library
     *
     * @return array
     */
    public function getPreparedQuery(): array
    {
        $builder = new QueryBuilder($this->prepareSearchCriteria());

        return $builder->setTermsAggregationBucketsLimits($this->getAggregationBucketsLimits())->build($this->getFilterLogic());
    }

    /**
     * @return array
     */
    #[ArrayShape([SearchCriteria::PAGINATION => "array"])]
    protected function getPreparedPagination(): array
    {
        $maxLimit = $this->getModel()->getResource()::INDEX['settings']['max_result_window']
            ?? QueryBuilder::ES_DEFAULT_MAX_RESULT_WINDOW;

        return [
            SearchCriteria::PAGINATION => [
                'limit' => min($this->limit, $maxLimit),
                'page'  => $this->page
            ]
        ];
    }

    /**
     * @return array
     */
    #[ArrayShape([SearchCriteria::PAGINATION => "array", QueryBuilder::EXCLUDE_FIELDS => "int[]|string[]", QueryBuilder::FULL_TEXT => "array", QueryBuilder::AGGREGATION => "array", QueryBuilder::POST_FILTER => "array", QueryBuilder::SORT_BY_SCRIPT => "array", SearchCriteria::SORT => "mixed", SearchCriteria::DATA_FIELDS => "int[]|string[]", SearchCriteria::FILTER => "array"])]
    protected function prepareSearchCriteria(): array
    {
        $searchCriteria = $this->getPreparedPagination();

        if (!empty($this->filterGroups)) {
            $searchCriteria[SearchCriteria::FILTER] = $this->prepareFilterByAnalyzer($this->filterGroups);
        }
        if (!empty($this->fieldsToSelect)) {
            $searchCriteria[SearchCriteria::DATA_FIELDS] = array_keys($this->fieldsToSelect);
        } else if (!empty($this->defaultFieldsToSelect)) {
            $searchCriteria[SearchCriteria::DATA_FIELDS] = array_keys($this->defaultFieldsToSelect);
        }

        if (!empty($this->order)) {
            $searchCriteria[SearchCriteria::SORT] = $this->order;
        }
        if (!empty($this->orderByScript)) {
            $searchCriteria[QueryBuilder::SORT_BY_SCRIPT] = $this->orderByScript;
        }

//        if (!empty($this->collapse)) {
//            $searchCriteria[QueryBuilder::COLLAPSE] = $this->collapse;
//        }
        if (!empty($this->postFilterGroups)) {
            $searchCriteria[QueryBuilder::POST_FILTER] = $this->postFilterGroups;
        }
        if (!empty($this->aggregationsToSelect)) {
            $searchCriteria[QueryBuilder::AGGREGATION] = $this->aggregationsToSelect;
        }
        if (!empty($this->fulltextFilters)) {
            $searchCriteria[QueryBuilder::FULL_TEXT] = $this->fulltextFilters;
        }
        if (!empty($this->fieldsToExclude)) {
            $searchCriteria[QueryBuilder::EXCLUDE_FIELDS] = array_keys($this->fieldsToExclude);
        }

        return $searchCriteria;
    }

    /**
     * Prepare search value for field by normalizer
     *
     * @param array $filterGroups
     *
     * @return array
     */
    public function prepareFilterByAnalyzer(array $filterGroups): array
    {
        $resourceModel = $this->getModel()->getResource();

        foreach ($filterGroups as &$groups) {
            if (empty($groups['group'])) {
                continue;
            }

            foreach ($groups['group'] as $key => &$item) {
                if (empty($item['operator']) || $item['operator'] !== SearchCriteria::LIKE) {
                    continue;
                }

                $analyzer = $resourceModel->getAnalyzerByField($item['field']);
                if (empty($analyzer) || !array_key_exists($analyzer, $this->getModel()::DATA_FIELD_ANALYZERS)) {
                    continue;
                }

                // @todo It is not correct. here $analyzer is array on null
                $func = $this->getModel()::DATA_FIELD_ANALYZERS[$analyzer];
                if (function_exists($func)) {
                    $item['value'] = $func($item['value']);
                }
            }
        }

        return $filterGroups;
    }

    /**
     * @return string
     */
    public function getFilterLogic(): string
    {
        return $this->filterLogic ?? BoolQuery::MUST;
    }

    /**
     * @return bool
     */
    protected function canMergeFilterGroups(): bool
    {
        return $this->filterLogic == BoolQuery::FILTER || $this->filterLogic == BoolQuery::MUST;
    }

    /**
     * Note: due to 7.0 changes in hits.total, the total now returned as not a real total documents number
     * by default it will return up to 10,000 count as accurate docs number
     * but if more then 10,000 needed - the logic must be reimplemented search API requests to use some scrolling
     * or to set track_total_hits to true
     *
     * @return $this
     */
    public function load(string $slug = null): static
    {
        // @todo slug is not implemented
        if ($this->isLoaded()) {

            return $this;
        }

        $this->rawSearchResults = $this->loadResourceData();

        $processedAggregations = $this->processAggregations($this->rawSearchResults['aggregations'] ?? []);
        $itemsCount = $processedAggregations[QueryBuilder::DISTINCT_COUNT_AGGREGATION_NAME] ?? null;
        unset($processedAggregations[QueryBuilder::DISTINCT_COUNT_AGGREGATION_NAME]);

        $this->setAggregation($processedAggregations);
        // See MAX_RESULTS_LIMIT constant in entity resource models
        // that constant limits the accuracy of total hits count on returned results
        $itemsCount = (int)($itemsCount ?? $this->rawSearchResults['hits']['total']['value'] ?? 0);

        if (!empty($this->rawSearchResults['hits']['hits'][0])) {
            $this->setTotalItemsCount($itemsCount);

            if (!empty($this->limit)) {
                $this->setTotalPagesCount($itemsCount, $this->limit);
            }

            $this->processHits($this->rawSearchResults['hits']['hits']);
        }

        $this->setIsLoaded();

        return $this;
    }

    /**
     * Get resource query and execute resource data retrieval
     *
     * @return array|callable
     */
    protected function loadResourceData(): array|callable
    {
        $query = $this->getPreparedQuery();

        $params = [];
        if (!empty($this->preference)) {
            $params[QueryBuilder::PREFERENCE] = $this->preference;
        }

        return $this->getAdapter()->search(
            $this->getReadIndex(),
            $query,
            $this->getTrackTotalHits(),
            $params
        );
    }

    /**
     * Get all data for collection w/o creating collection object items
     *
     * @return array
     */
    public function getData(): array
    {
        if ($this->data === null) {
            $currentAggregations = $this->aggregationsToSelect;
            $this->aggregationsToSelect = [];
            $query = $this->getPreparedQuery();
            $this->aggregationsToSelect = $currentAggregations;
            $this->data = $this->getAdapter()->fetchAll($this->getReadIndex(), $query);
        }

        return $this->data;
    }

    /**
     * @param string $indexName
     *
     * @return $this
     */
    public function setReadIndex(string $indexName): static
    {
        $this->readIndex = $indexName;
    }

    /**
     * @return string
     */
    public function getReadIndex(): string
    {
        return $this->readIndex ?? $this->getModel()->getResource()->getReadAlias();
    }

    /**
     * Set total hits count accuracy parameter when returning search results
     *
     * @return int|null
     */
    protected function getTrackTotalHits(): int|null
    {
        $trackTotalHits = null;
        $resourceModel = $this->getModel()->getResource();
        if (defined(get_class($resourceModel) . '::MAX_RESULTS_LIMIT')) {
            $trackTotalHits = $resourceModel::MAX_RESULTS_LIMIT;
        }

        return $trackTotalHits;
    }

    /**
     * Add items to collection based on data from ElasticSearch search hits
     *
     * @param array $hits
     */
    protected function processHits(array $hits): void
    {
        foreach ($hits as $key => $value) {
            $object = $this->getModel()->factoryCreate($value['_source']);
            $object->setId($value['_id']);
            $object->setHasDataChanges(false);
            $this->addItem($object);
        }
    }

    /**
     * @todo for now real_count distinct subaggregations are disabled in QueryBuilder for performance reasons
     *
     * @param array $rawAggregations
     *
     * @return array
     */
    protected function processAggregations(array $rawAggregations): array
    {
        $result = [];
        foreach ($rawAggregations as $name => $aggregation) {
            // This case can be from ordering aggregation logic, so skip it
            if (!is_array($aggregation)) {
                continue;
            }

            $originName = $name;
            if (!empty($aggregation[$name])) {
                $aggregation = $aggregation[$name];
            }

            /**
             * Currently ES sorting aggregations in standard way by bucket count.
             * But it is not correct when using `collapse` approach - because real count is grouped by some field
             * and you will see that counts are not in descending order.
             *
             * Thus $mustSort was implemented to do application level sorting by real_count data values
             */
            $explodedArr = explode(QueryBuilder::TERM_AGGREGATION_PREFIX, $name);
            $mustSort = false;
            if (isset($explodedArr[1])) {
                $mustSort = !empty($this->collapse);
                $name = $explodedArr[1];
            } else {
                $name = reset($explodedArr);
            }

            if (key_exists('value', $aggregation)) {
                $result[$name] = $aggregation['value'];
            } else if (key_exists('buckets', $aggregation)) {
                $buckets = [];
                foreach ($aggregation['buckets'] as $bucket) {
                    $key = $bucket['key'];
                    // for composite aggregation $bucket['key'] this is array, eg [key => [field => value]]
                    if (is_array($key) && !empty($key[$name])) {
                        $key = $key[$name];
                    }

                    /**
                     * Buckets when collapse based aggregation counts used:
                     */
//                    "product.categories" : {
//                        "doc_count_error_upper_bound" : 0,
//                        "sum_other_doc_count" : 0,
//                        "buckets" : [
//                          {
//                            "key" : "ctgr_Iqn0nXjx7V2QcC",
//                            "doc_count" : 2,
//                            "real_count" : {
//                              "value" : 1
//                            }
//                          }
//                        ]
//                    }

                    /**
                     * Buckets when nested based aggregation counts are used
                     */
//                    "term:variation.merchant_id" : {
//                        "doc_count_error_upper_bound" : 0,
//                        "sum_other_doc_count" : 0,
//                        "buckets" : [
//                          {
//                            "key" : 2,
//                            "doc_count" : 7,
//                            "real_count" : {
//                              "doc_count" : 2
//                            }
//                          }
//                        ]
//                      }

                    // QueryBuilder::DISTINCT_COUNT_AGGREGATION_NAME is used to get proper parent docs count
                    // either when collapse based aggregation counts used or when nested based aggregation counts used
                    $buckets[strval($key)] = $bucket[QueryBuilder::DISTINCT_COUNT_AGGREGATION_NAME]['value'] ??
                        $bucket[QueryBuilder::DISTINCT_COUNT_AGGREGATION_NAME]['doc_count'] ??
                        $bucket['doc_count'];
                }

                if ($mustSort) {
                    arsort($buckets);
                }

                $result[$name] = $buckets;
            } else if (key_exists($originName, $aggregation)) {
                $buckets = [];
                foreach ($aggregation[$originName]['buckets'] as $bucket) {
                    $key = $bucket['key'];

                    // QueryBuilder::DISTINCT_COUNT_AGGREGATION_NAME is used to get proper parent docs count
                    // either when collapse based aggregation counts used or when nested based aggregation counts used
                    $buckets[strval($key)] = $bucket[QueryBuilder::DISTINCT_COUNT_AGGREGATION_NAME]['value'] ??
                        $bucket[QueryBuilder::DISTINCT_COUNT_AGGREGATION_NAME]['doc_count'] ??
                        $bucket['doc_count'];
                }
                if ($mustSort) {
                    arsort($buckets);
                }
                $result[$name] = $buckets;
            } else {
                $result[$name] = $aggregation;
            }
        }

        return $result;
    }

    /**
     * @param null|string $aggregationName
     *
     * @return mixed
     */
    public function getAggregation(?string $aggregationName = null): mixed
    {
        if ($aggregationName === null) {

            return $this->aggregation;
        }

        return $this->aggregation[$aggregationName] ?? null;
    }

    /**
     * Remove unwanted aggregation from collection
     *
     * @param string $aggregationName
     *
     * @return $this
     */
    public function unsetAggregation(string $aggregationName): self
    {
        unset($this->aggregation[$aggregationName]);

        return $this;
    }

    /**
     * @param array $data
     *
     * @return $this
     */
    protected function setAggregation(array $data = []): self
    {
        $this->aggregation = $data;

        return $this;
    }

    /**
     * @param array $batch
     * @param bool $directCreate
     * @param string $comment
     *
     * @return AbstractElasticsearchCollection
     */
    public function bulkCreate(array $batch, bool $directCreate = false, string $comment = 'System Create'): static
    {
        /** @var static $collection */
        $collection = $this->factoryCreate();

        foreach ($batch as $item) {
            /** @var AbstractElasticsearchModel $object */
            $object = $this->getModel()->factoryCreate($item);
            $object->isObjectNew(true);
            $object->setId($object->generateUniqueId());
            // Set created_at date and some default data in direct mode
            if ($directCreate) {
                $object->prepareSystemData();
            }
            $collection->addItem($object);
        }

        $uniqueFieldValues = [];
        if (!$directCreate) {
            $collection->bulkBeforeSave();
            $uniqueFields = $this->getModel()->getResource()->getUniqueFields();
        }

        // Collect and Validate unique field values after preparing all data
        // Avoid this checking during direct create, we assume input data is reliable
        if (!empty($uniqueFields)) {
            foreach ($collection as $item) {
                $this->collectBulkUniqueValues($uniqueFieldValues, $item, $uniqueFields);
            }
            $this->validateBulkDataUniqueness($uniqueFieldValues, $collection);
        }

        $originalIds = $collection->getIds();
        $resultedIds = $this->getAdapter()->enforceWriteConnection(function () use ($collection) {
            return $this->getModel()->getResource()->addRecords($this->getModel(), $collection->toArray(true));
        });

        // cleanup resulting collection so that result indicates real persisted data
        foreach ($originalIds as $itemId) {
            if (!in_array($itemId, $resultedIds)) {
                $collection->removeItem($itemId);
            }
        }

        if ($collection->isEmpty()) {
            throw new \DomainException('Cannot create entities - data could not persist');
        }

        if (!$directCreate) {
            $collection->bulkAfterSave();
        }

        $eventName = $directCreate
            ? $this->getModel()::ENTITY . '.collection.direct_mass_add.after'
            : $this->getModel()::ENTITY . '.collection.mass_add.after';

        event($eventName, ['params' => ['collection' => $collection, 'comment' => $comment]]);

        return $collection;
    }

    /**
     * @param array $batch
     * @param bool $directUpdate
     * @param string $comment
     *
     * @return $this
     *
     * @throws EmptyCollectionException
     * @throws NoDataChangesException
     */
    public function bulkUpdate(array $batch, bool $directUpdate = false, string $comment = 'System Update'): static
    {
        $ids = array_column($batch, AbstractModel::ID_FIELD_NAME);
        if (empty($ids)) {
            throw new \InvalidArgumentException('Cannot update records: no IDs found in data update batch');
        }

        // @todo in some cases bulkUpdate() is originating from collection of entities, not from a raw arrays, in those cases no need for an additional load()
        /** @var $this $collection */
        $collection = $this->factoryCreate();
        $collection->addIdsFilter($ids)
            ->limit(count($ids))
            ->load();

        if ($collection->isEmpty()) {
            throw new EmptyCollectionException('Cannot update records - specified IDs do not exist');
        }

        // Filter batch by existing IDs
        $batch = array_filter($batch, function ($element) {
            return key_exists(AbstractModel::ID_FIELD_NAME, $element);
        });

        foreach ($batch as $item) {
            $itemId = $item[AbstractModel::ID_FIELD_NAME];
            $object = $collection->getItemById($itemId);
            if ($object === null) {
                continue;
            }

            $object->addData($item);
            if (!$object->hasDataChanges()) {
                $collection->removeItem($itemId);
                continue;
            }
            // Set update_at date and some default data in direct mode
            if ($directUpdate) {
                $object->prepareSystemData();
            }
        }

        /** No changes detected */
        if ($collection->isEmpty()) {
            throw new NoDataChangesException();
        }

        $uniqueFieldValues = [];
        if (!$directUpdate) {
            $collection->bulkBeforeSave();
            $uniqueFields = $this->getModel()->getResource()->getUniqueFields();
        }

        // Collect and Validate unique field values after preparing all data
        // Avoid this checking during direct update, we assume those changes are reliable
        if (!empty($uniqueFields)) {
            foreach ($collection as $item) {
                $this->collectBulkUniqueValues($uniqueFieldValues, $item, $uniqueFields, true);
            }
            $this->validateBulkDataUniqueness($uniqueFieldValues, $collection);
        }

        $originalIds = $collection->getIds();
        $resultedIds = $this->getAdapter()->enforceWriteConnection(function () use ($collection) {
            return $this->getModel()->getResource()->updateRecords($collection->toArray(true));
        });

        // cleanup resulting collection so that result indicates real persisted data
        foreach ($originalIds as $itemId) {
            if (!in_array($itemId, $resultedIds)) {
                $collection->removeItem($itemId);
            }
        }

        if ($collection->isEmpty()) {
            throw new EmptyCollectionException('Cannot update entities - data could not persist');
        }

        if (!$directUpdate) {
            $collection->bulkAfterSave();
        }

        $eventName = $directUpdate
            ? $this->getModel()::ENTITY . '.collection.direct_mass_update.after'
            : $this->getModel()::ENTITY . '.collection.mass_update.after';

        event($eventName, ['params' => ['collection' => $collection, 'comment' => $comment]]);

        return $collection;
    }

    /**
     * Worker: Product Data Post Processor
     *    index: trigger (score), trigger (stats), trigger (etl)
     *        100 batch trigger score -> CREATE new stats triggers
     *        100 batch trigger stats (old) -> CREATE new ETL triggers
     *        100 batch trigger ETL (old)
     */

    /**
     * @return static
     */
    public function bulkBeforeSave(): static
    {
        /** @var AbstractElasticsearchModel $item */
        foreach ($this->getItems() as $item) {
            $item->prepareData();
        }

        return $this;
    }

    /**
     * @return $this
     *
     * @todo not implemented anywhere, must be implemented at least for Categories and Products
     */
    public function bulkAfterSave(): static
    {
        return $this;
    }

    /**
     * @param array $filter
     * @param array $updateData
     * @param bool  $ignoreOnConflict
     *
     * @return int
     * @todo beforeSave and afterSave logic not implemented here
     *
     */
    public function updateRecordsByFilter(array $filter, array $updateData, bool $ignoreOnConflict = false): int
    {
        return $this->model->getResource()->updateRecordsByFilter($filter, $updateData, $ignoreOnConflict);
    }

    /**
     * @param array         $uniqueFieldValues
     * @param AbstractModel $object
     * @param array         $uniqueFieldsList
     * @param bool          $isUpdate
     */
    protected function collectBulkUniqueValues(
        array &$uniqueFieldValues, AbstractModel $object, array $uniqueFieldsList, bool $isUpdate = false
    ) {
        foreach ($uniqueFieldsList as $field) {
            if (!$object->hasData($field)) {
                continue;
            }

            $params = [$field => $object->getDataUsingMethod($field)];
            $uniquenessScopeFields = $object->getResource()->getUniquenessScopeFields();
            if (!empty($uniquenessScopeFields)) {
                foreach ($uniquenessScopeFields as $fieldName) {
                    $params[$fieldName] = $object->getDataUsingMethod($fieldName);
                }
            }

            if ($isUpdate) {
                $params = array_merge($params, ['id' => [SearchCriteria::NEQ => $object->getId()]]);
            }

            $uniqueFieldValues[] = $params;
        }
    }

    /**
     * Ex.: ((url_path = 'value' AND is_virtual = VIRTUAL_VALUE) OR (name = 'value AND is_virtual = VIRTUAL_VALUE))
     *
     * When updating data exclude current object id when checking existing data
     *
     * @param array              $uniqueFieldValues
     * @param AbstractCollection $inputCollection
     *
     * @return bool
     * @throws EntityFieldNotUniqueException
     */
    protected function validateBulkDataUniqueness(array $uniqueFieldValues, AbstractCollection $inputCollection): bool
    {
        if (empty($uniqueFieldValues)) {

            return true;
        }

        /** @var $this $collection */
        $collection = $this->factoryCreate()->setFilterLogic(BoolQuery::SHOULD);
        $resourceModel = $collection->getModel()->getResource();
        $preparedUniqueFields = $resourceModel->getPreparedUniqueFieldsForDateRetrieval();

        foreach ($uniqueFieldValues as $group) {
            $collection->addFieldToFilter($group, null, SearchCriteria::CONDITION_AND);
        }

        $collection->addFieldToSelect($preparedUniqueFields)
            ->limit($resourceModel::MAX_RESULTS_LIMIT)
            ->load();

        if ($collection->count() < 1) {

            return true;
        }

        $conflicts = $conflictedFields = [];
        foreach ($inputCollection as $object) {
            foreach ($collection as $existingDataModel) {
                foreach ($preparedUniqueFields as $uniqueField) {
                    if (empty($object->getData($uniqueField))) {
                        continue;
                    }

                    $existingValue = $existingDataModel->getData($uniqueField);
                    if ($object->getData($uniqueField) == $existingValue) {
                        $conflicts[$existingDataModel->getId()][$uniqueField] = $existingValue;
                        $conflictedFields[$uniqueField] = true;

                        break 2;
                    }
                }
            }
        }

        if (!empty($conflicts)) {
            $report = [];
            foreach ($conflicts as $id => $fields) {
                $attrs = [];
                foreach ($fields as $field => $value) {
                    $attrs[] = $field . '=' . $value;
                }
                $report[] = $id . ':' . implode(',', $attrs);
            }
            throw new EntityFieldNotUniqueException(
                'COLLECTION',
                $report,
                array_keys($conflictedFields)
            );
        }

        return true;
    }

    /**
     * Get converted/prepared data collection
     *
     * @return array
     */
    public function getMappedData(): array
    {
        $data = [];
        if ($this->count() > 0) {
            /** @var AbstractElasticsearchModel $item */
            foreach ($this->getItems() as $item) {
                $data[] = $item->getMappedData();
            }
        }

        return $data;
    }

    /**
     * @return int
     */
    public function getSize(): int
    {
        $query = $this->getPreparedQuery();
        $model = $this->getModel();

        return $this->getAdapter()->countDocs($model->getResource()->getReadAlias(), $query);
    }

    /**
     * @return $this
     */
    public function clear(): static
    {
        parent::clear();

        $this->aggregation = [];
        $this->fulltextFilters = [];
        $this->aggregationsToSelect = [];
        $this->collapse = [];
        $this->rawSearchResults = [];
        $this->postFilterGroups = [];
        $this->orderByScript = [];
        $this->preference = null;
        $this->fieldsToExclude = [];

        return $this;
    }

    /**
     * @return array
     *
     * @throws FilterNotSpecifiedException
     */
    public function getPreparedDeleteQuery(): array
    {
        if (empty($this->filterGroups)) {
            throw new FilterNotSpecifiedException(
                sprintf('No filter specified for when deleting entity "%s" by filter', $this->getModel()::ENTITY)
            );
        }

        $searchCriteria[SearchCriteria::FILTER] = $this->filterGroups;

        $builder = new QueryBuilder($searchCriteria);

        return $builder->build();
    }

    /**
     * @return mixed
     */
    public function removeRecords(): mixed
    {
        return $this->getAdapter()->enforceWriteConnection(function () {
            return $this->model->getResource()->deleteRecords($this->getPreparedDeleteQuery());
        });
    }
}