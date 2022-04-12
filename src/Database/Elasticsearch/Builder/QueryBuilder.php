<?php
declare(strict_types=1);

namespace Levtechdev\Simpaas\Database\ElasticSearch\Builder;

use ONGR\ElasticsearchDSL\Aggregation\AbstractAggregation;
use ONGR\ElasticsearchDSL\Aggregation\Bucketing\CompositeAggregation;
use ONGR\ElasticsearchDSL\Aggregation\Bucketing\FilterAggregation;
use ONGR\ElasticsearchDSL\Aggregation\Bucketing\HistogramAggregation;
use ONGR\ElasticsearchDSL\Search;
use ONGR\ElasticsearchDSL\Query\Compound\BoolQuery;
use ONGR\ElasticsearchDSL\Query\FullText\MultiMatchQuery;
use ONGR\ElasticsearchDSL\Query\TermLevel\ExistsQuery;
use ONGR\ElasticsearchDSL\Query\TermLevel\IdsQuery;
use ONGR\ElasticsearchDSL\Query\TermLevel\RangeQuery;
use ONGR\ElasticsearchDSL\Query\TermLevel\RegexpQuery;
use ONGR\ElasticsearchDSL\Query\TermLevel\TermQuery;
use ONGR\ElasticsearchDSL\Query\TermLevel\TermsQuery;
use ONGR\ElasticsearchDSL\Query\TermLevel\WildcardQuery;
use ONGR\ElasticsearchDSL\Sort\FieldSort;
use ONGR\ElasticsearchDSL\Aggregation\Bucketing\TermsAggregation;
use ONGR\ElasticsearchDSL\Aggregation\Metric\ValueCountAggregation;
use ONGR\ElasticsearchDSL\Aggregation\Metric\SumAggregation;
use ONGR\ElasticsearchDSL\Aggregation\Metric\AvgAggregation;
use ONGR\ElasticsearchDSL\Aggregation\Metric\MinAggregation;
use ONGR\ElasticsearchDSL\Aggregation\Metric\MaxAggregation;
use ONGR\ElasticsearchDSL\Aggregation\Metric\CardinalityAggregation;
use Elasticsearch\Common\Exceptions\InvalidArgumentException;
use Levtechdev\Simpaas\Database\SearchCriteria;

class QueryBuilder
{
    const ES_DEFAULT_MAX_RESULT_WINDOW    = 10000;
    const DEFAULT_TERMS_AGGREGATION_BUCKETS_LIMIT = 60;

    const FULL_TEXT      = 'full_text';
    const AGGREGATION    = 'aggregation';
    const COLLAPSE       = 'collapse';
    const POST_FILTER    = 'post_filter';
    const PREFERENCE     = 'preference';
    const EXCLUDE_FIELDS = 'exclude_fields';

    const SORT_BY_SCRIPT          = 'sort_by_script';
    const TERM_AGGREGATION_PREFIX = 'term:';
    const SOURCE                  = '_source';

    /**
     *  Aggregation types
     */
    const AGGREGATION_HISTOGRAM   = 'histogram';
    const AGGREGATION_TERMS       = 'terms';
    const AGGREGATION_FILTER      = 'filter';
    const AGGREGATION_MIN         = 'min';
    const AGGREGATION_MAX         = 'max';
    const AGGREGATION_AVG         = 'avg';
    const AGGREGATION_SUM         = 'sum';
    const AGGREGATION_COUNT       = 'count';
    const AGGREGATION_CARDINALITY = 'cardinality';
    const AGGREGATION_COMPOSITE   = 'composite';

    const DISTINCT_COUNT_AGGREGATION_NAME = 'real_count';

    const SUPPORTED_AGGREGATIONS = [
        self::AGGREGATION_HISTOGRAM   => HistogramAggregation::class,
        self::AGGREGATION_TERMS       => TermsAggregation::class,
        self::AGGREGATION_FILTER      => FilterAggregation::class,
        self::AGGREGATION_MIN         => MinAggregation::class,
        self::AGGREGATION_MAX         => MaxAggregation::class,
        self::AGGREGATION_AVG         => AvgAggregation::class,
        self::AGGREGATION_SUM         => SumAggregation::class,
        self::AGGREGATION_COUNT       => ValueCountAggregation::class,
        self::AGGREGATION_CARDINALITY => CardinalityAggregation::class,
        self::AGGREGATION_COMPOSITE   => CompositeAggregation::class,
    ];

    const AGGREGATION_HISTOGRAM_DEFAULT_INTERVAL = 50;

    const MUST     = BoolQuery::MUST;
    const MUST_NOT = BoolQuery::MUST_NOT;

    /** @var array  */
    protected array $boolOperator = [
        SearchCriteria::CONDITION_AND => BoolQuery::FILTER,
        SearchCriteria::CONDITION_OR  => BoolQuery::SHOULD
    ];

    const ALPHABETICAL_ORDER = 'alphabetical';

    protected array $searchCriteria;
    protected array $postSearchCriteria;
    protected array $aggregation;
    protected array $fullText;
    protected array $sort;
    protected array $sortByScript;
    protected array $fields;
    protected array $excludeFields;
    protected array $pagination;
    protected array $collapse;

    protected Search $searchQuery;

    /** @var array */
    protected array $termsAggregationBucketsLimits = [];

    /**
     * Filter constructor.
     *
     * @param array $searchCriteria
     */
    public function __construct(array $searchCriteria)
    {
        $this->searchQuery = new Search();

        if (isset($searchCriteria[SearchCriteria::FILTER])) {
            $this->searchCriteria = $searchCriteria[SearchCriteria::FILTER];
        }
        if (isset($searchCriteria[self::POST_FILTER])) {
            $this->postSearchCriteria = $searchCriteria[self::POST_FILTER];
        }

//        $this->sort = [['field' => ElasticSearch::TYPE]];

        if (isset($searchCriteria[SearchCriteria::SORT])) {
            $this->sort = $searchCriteria[SearchCriteria::SORT];
        }
        if (isset($searchCriteria[self::SORT_BY_SCRIPT])) {
            $this->sortByScript = $searchCriteria[self::SORT_BY_SCRIPT];
        }
        if (isset($searchCriteria[SearchCriteria::DATA_FIELDS])) {
            $this->fields = $searchCriteria[SearchCriteria::DATA_FIELDS];
        }
        if (isset($searchCriteria[self::EXCLUDE_FIELDS])) {
            $this->excludeFields = $searchCriteria[self::EXCLUDE_FIELDS];
        }

        $this->pagination = [
            'page'  => SearchCriteria::DEFAULT_PAGE,
            'limit' => SearchCriteria::DEFAULT_PAGE_SIZE
        ];

        if (isset($searchCriteria[SearchCriteria::PAGINATION])) {
            $limit = $searchCriteria[SearchCriteria::PAGINATION]['limit'] ?? SearchCriteria::DEFAULT_PAGE_SIZE;
            $page = $searchCriteria[SearchCriteria::PAGINATION]['page'] ?? SearchCriteria::DEFAULT_PAGE;
            $page = $page <= 0 ? SearchCriteria::DEFAULT_PAGE : $page;

            $this->pagination = [
                'page'  => (int)$page,
                'limit' => (int)$limit
            ];
        }

        if (isset($searchCriteria[self::FULL_TEXT])) {
            $this->fullText = $searchCriteria[self::FULL_TEXT];
        }
        if (isset($searchCriteria[self::AGGREGATION])) {
            $this->aggregation = $searchCriteria[self::AGGREGATION];
        }
        if (isset($searchCriteria[self::COLLAPSE])) {
            $this->collapse = $searchCriteria[self::COLLAPSE];
        }
    }

    /**
     * @param string $filterSwitch
     *
     * @return array
     */
    public function build(string $filterSwitch = BoolQuery::FILTER): array
    {
        $filterQuery = null;
        $searchQuery = $this->searchQuery;

        if (!empty($this->searchCriteria)) {
            $filterQuery = $this->prepareFilterQuery($this->searchCriteria, $filterSwitch);
        }

        if (!empty($this->fullText)) {
            $fullTextSearchQueries = $this->prepareFullTexSearchQuery($this->fullText);

            if (empty($filterQuery)) {
                $filterQuery = new BoolQuery();
            }

            foreach($fullTextSearchQueries as $fullTextSearchQuery) {
                $filterQuery->add($fullTextSearchQuery['query'], $fullTextSearchQuery['type']);
            }
        }

        if (!empty($this->postSearchCriteria)) {
            $postFilterBoolQuery = $this->prepareFilterQuery($this->postSearchCriteria, new BoolQuery());
            $searchQuery->addPostFilter($postFilterBoolQuery);
        }

        if (!empty($this->aggregation)) {
            $subAggregation = null;
            /**
             * Add cardinality aggregation to retrieve correct real documents count when collapsing by field
             * It is expected that collapse field contains a multi-field "hash" with type "murmur3"
             */
            if (!empty($this->collapse)) {
                $this->aggregation[self::AGGREGATION_CARDINALITY] = [
                    self::DISTINCT_COUNT_AGGREGATION_NAME => $this->collapse['field'] . '.hash'
                ];

                /**
                 * Add sub aggregation to each aggregation to retrieve correct bucket counts when collapsing by field
                 */
                $subAggregation = new CardinalityAggregation(self::DISTINCT_COUNT_AGGREGATION_NAME);
                $subAggregation->setField($this->collapse['field'] . '.hash');
            }

            /**
             * Add sub aggregation to each aggregation to retrieve correct bucket counts when collapsing by field
             * Collapse bypass mode - used only when custom ES multi search logic is in place
             */
            $cardinalityAggFieldName = $this->aggregation[self::AGGREGATION_CARDINALITY]
                                       [QueryBuilder::DISTINCT_COUNT_AGGREGATION_NAME] ?? null;

            if ($subAggregation === null && !empty($cardinalityAggFieldName)) {
                $subAggregation = new CardinalityAggregation(self::DISTINCT_COUNT_AGGREGATION_NAME);
                $subAggregation->setField($cardinalityAggFieldName);
            }

            $objectsAggregations = [];
            foreach ($this->aggregation as $aggregationType => $aggregation) {
                if (is_numeric($aggregationType)) {
                    $objectsAggregations = array_merge(
                        $objectsAggregations,
                        $this->addAggregation($aggregation, self::AGGREGATION_TERMS, $subAggregation)
                    );
                } elseif ($aggregationType === self::AGGREGATION_COMPOSITE) {
                    if (!empty($aggregation['fields'])) {
                        $fields = [$aggregation['fields']];
                    } else {
                        $fields = array_column($aggregation, 'fields');
                    }

                    if (empty($fields)) {
                        continue;
                    }

                    $objectAggregationsComposite = $this->addAggregation(
                        $fields, self::AGGREGATION_TERMS, $subAggregation
                    );
                    foreach ($objectAggregationsComposite as $objectAggregation) {
                        $compositeAgg = new CompositeAggregation(
                            $objectAggregation->getName(),
                            [$objectAggregation->getName() => $objectAggregation]
                        );
                        $compositeAgg->setSize($aggregation['size'] ?? static::DEFAULT_TERMS_AGGREGATION_BUCKETS_LIMIT);
                        if (!empty($aggregation['after_key'])) {
                            $compositeAgg->setAfter($aggregation['after_key']);
                        }

                        $objectsAggregations[] = $compositeAgg;
                    }
                } else {
                    $objectsAggregations = array_merge(
                        $objectsAggregations,
                        $this->addAggregation($aggregation, $aggregationType, $subAggregation)
                    );
                }
            }

            foreach ($objectsAggregations as $objectAggregation) {
                $searchQuery->addAggregation($objectAggregation);
            }
        }

        if (!empty($this->sort)) {
            $sorts = $this->prepareSortQuery($this->sort);

            foreach ($sorts as $sort) {
                $searchQuery->addSort($sort);
            }
        }

        if (!empty($this->pagination)) {
            $searchQuery->setFrom(($this->pagination['page'] - 1) * $this->pagination['limit']);
            $searchQuery->setSize($this->pagination['limit']);
        }

        if (!empty($filterQuery)) {
            $searchQuery->addQuery($filterQuery);
        }

        $query = $searchQuery->toArray();

        if (!empty($this->collapse)) {
            $query['collapse'] = $this->collapse;
        }
        if ($this->sortByScript) {
            foreach ($this->sortByScript as $script) {
                $query['sort'][] = $script;
            }
        }

        list ($includes, $excludes) = $this->prepareFields($this->fields ?? [], $this->excludeFields ?? []);
        if (!empty($includes)) {
            $query['_source'] = $includes;
        }

        if (!empty($excludes)) {
            $query['_source'] = $excludes;
        }

        return $query;
    }

    /**
     * @param array|null $fieldSearchCriteria
     * @param array|null $fieldExcludesSearchCriteria
     *
     * @return array[]
     */
    public function prepareFields(?array $fieldSearchCriteria, ?array $fieldExcludesSearchCriteria = []): array
    {
        $includes = [];
        if (!empty($fieldSearchCriteria)) {
            $fields = array_unique($fieldSearchCriteria);
            foreach ($fields as &$field) {
                if ($field == 'id') {
                    $field = '_id';
                    break;
                }
            }

            $includes['includes'] = $fields;
        }

        $excludes = [];
        if (!empty($fieldExcludesSearchCriteria)) {
            $fields = array_unique($fieldExcludesSearchCriteria);

            $excludes['excludes'] = $fields;
        }

        return [$includes, $excludes];
    }

    /**
     * @param array $sortSearchCriteria
     *
     * @return array
     */
    public function prepareSortQuery(array $sortSearchCriteria): array
    {
        $sorts = [];
        foreach ($sortSearchCriteria as $order) {
            $sort = new FieldSort(
                $order['field'],
                $order['order'] ?? $order['direction'] ?? SearchCriteria::SORT_ORDER_ASC,
                // To ignore errors when sorting on not existing fields
                [
                    'missing'       => '_last',
                    'unmapped_type' => 'keyword'
                ]
            );
            $sorts[] = $sort;
        }

        return $sorts;
    }

    /**
     * @param array $fullTestSearchCriteria
     *
     * @return array
     */
    public function prepareFullTexSearchQuery(array $fullTestSearchCriteria): array
    {
        $fulltextSearchQueries = [];
        foreach ($fullTestSearchCriteria as $fullTextSearchData) {
            if (!empty($fullTextSearchData['value'])) {
                $multiMatchQuery = new MultiMatchQuery(
                    $fullTextSearchData['fields'],
                    $fullTextSearchData['value'],
                    $fullTextSearchData['parameters'] ?? []
                );
                $type = $fullTextSearchData['match_type'] ?? self::MUST;

                $fulltextSearchQueries[] = ['query' => $multiMatchQuery, 'type' => $type];
            }
        }

        return $fulltextSearchQueries;
    }

    /**
     * @param array $limits
     *
     * @return $this
     */
    public function setTermsAggregationBucketsLimits(array $limits): self
    {
        $this->termsAggregationBucketsLimits = $limits;

        return $this;
    }

    /**
     * Getting default limit or specific aggregation limit which was set manually
     *
     * @param string|null $aggregationName
     *
     * @return int
     */
    public function getTermsAggregationBucketsLimit(?string $aggregationName = null): int
    {
        if ($aggregationName === null) {

            return static::DEFAULT_TERMS_AGGREGATION_BUCKETS_LIMIT;
        }

        if (!empty($this->termsAggregationBucketsLimits[$aggregationName])) {

            return $this->termsAggregationBucketsLimits[$aggregationName];
        }

        $prefixName = QueryBuilder::TERM_AGGREGATION_PREFIX . $aggregationName;
        if (!empty($this->termsAggregationBucketsLimits[$prefixName])) {

            return $this->termsAggregationBucketsLimits[$prefixName];
        }

        return static::DEFAULT_TERMS_AGGREGATION_BUCKETS_LIMIT;
    }

    /**
     * @param array     $searchFilterGroups
     * @param string    $filterSwitch
     *
     * @return BoolQuery
     */
    public function prepareFilterQuery(array $searchFilterGroups, string $filterSwitch = BoolQuery::FILTER): BoolQuery
    {
        $filterQuery = new BoolQuery();

        $filterSwitch = !in_array($filterSwitch,
            [BoolQuery::FILTER, BoolQuery::MUST, BoolQuery::MUST_NOT, BoolQuery::SHOULD])
            ? BoolQuery::FILTER
            : $filterSwitch;

        foreach ($searchFilterGroups as $group) {
            $condition = !empty($group['condition']) ? strtolower($group['condition']) : null;

            // Group must contain 2 or more filter in order for group condition to be taken into account
            // otherwise group with one filter will simply be a part of main bool query where $filterSwitch is main bool operator
            $boolOperator = (count($group['group']) >= 2 && $condition && !empty($this->boolOperator[$condition]))
                ? $this->boolOperator[$condition]
                : $filterSwitch;

            $queryGroup = new BoolQuery();

            foreach ($group['group'] as $filter) {
                if (!isset($filter['operator'])
                    || !isset($filter['field'])
                    || (
                        !isset($filter['value'])
                        && !in_array($filter['operator'], [SearchCriteria::NOT_EXISTS, SearchCriteria::EXISTS])
                    )
                ) {
                    continue;
                }

                $filter['field'] = strtolower($filter['field']);

                if ($filter['field'] == 'id') {
                    $filter['field'] = '_id';
                }

                switch ($filter['operator']) {
                    case SearchCriteria::EQ:
                        $query = new TermQuery($filter['field'], $filter['value']);
                        break;
                    case SearchCriteria::NEQ:
                        $term = new TermQuery($filter['field'], $filter['value']);
                        $query = new BoolQuery();
                        $query->add($term, BoolQuery::MUST_NOT);
                        break;
                    case SearchCriteria::BETWEEN:
                        $query = new RangeQuery($filter['field'],
                            ['from' => $filter['value']['from'], 'to' => $filter['value']['to']]);
                        break;
                    case SearchCriteria::LIKE:
                        $query = new WildcardQuery($filter['field'], $filter['value']);
                        break;
                    case SearchCriteria::NOT_LIKE:
                        $term = new WildcardQuery($filter['field'], $filter['value']);
                        $query = new BoolQuery();
                        $query->add($term, BoolQuery::MUST_NOT);
                        break;
                    case SearchCriteria::REGEXP:
                        $query = new RegexpQuery($filter['field'], $filter['value']);
                        break;
                    case SearchCriteria::NOT_REGEXP:
                        $term = new RegexpQuery($filter['field'], $filter['value']);
                        $query = new BoolQuery();
                        $query->add($term, BoolQuery::MUST_NOT);
                        break;
                    case SearchCriteria::EXISTS:
                        $query = new ExistsQuery($filter['field']);
                        break;
                    case SearchCriteria::NOT_EXISTS:
                        $term = new ExistsQuery($filter['field']);
                        $query = new BoolQuery();
                        $query->add($term, BoolQuery::MUST_NOT);
                        break;
                    case SearchCriteria::NOT_IN:
                        $values = is_array($filter['value']) ? $filter['value'] : [$filter['value']];
                        $values = array_values(array_unique($values));
                        if (!empty($values)) {
                            $term = new TermsQuery($filter['field'], $values);
                            $query = new BoolQuery();
                            $query->add($term, BoolQuery::MUST_NOT);
                        }
                        break;
                    case SearchCriteria::IN:
                        $values = is_array($filter['value']) ? $filter['value'] : [$filter['value']];
                        $values = array_values(array_unique($values));
                        if (!empty($values)) {
                            if (in_array($filter['field'], ['_id', 'id'])) {
                                $query = new IdsQuery($values);
                                break;
                            }
                            $query = new TermsQuery($filter['field'], $values);
                        }
                        break;
                    case SearchCriteria::IN_OR_LIKES:
                        $query = new BoolQuery();
                        foreach ($filter['value'] as $value) {
                            $subQuery = new WildcardQuery($filter['field'], $value);
                            $query->add($subQuery, BoolQuery::SHOULD);
                        }
                        break;
                    case SearchCriteria::LT:
                    case SearchCriteria::GT:
                    case SearchCriteria::LTE:
                    case SearchCriteria::GTE:
                        $query = new RangeQuery($filter['field'], [$filter['operator'] => $filter['value']]);
                        break;
                    default:
                        throw new InvalidArgumentException(sprintf('Specified unsupported operator "%s" in search filter conditions',
                            $filter['operator']));
                }

                if (isset($query)) {
                    $queryGroup->add($query, $boolOperator);
                }
            }

            if (!empty($queryGroup->getQueries())) {
                $filterQuery->add($queryGroup, $filterSwitch);
            }
        }

        return $filterQuery;
    }

    /**
     * Terms aggregation example:
     * $aggregation = 'field_name'
     *
     * Histogram aggregation example:
     *
     * Specify interval
     * $aggregation =  [
     *     'histogram' => ['price.final_price' => 50],
     * ]
     *
     * No interval specified - default is 50
     * $aggregation =  [
     *     'histogram' => ['price.final_price', 'stock.qty']
     * ]
     *
     * Metric Aggregation example:
     * $aggregation =  [
     *     'sum' => ['performance_sum' => 'scores.performance', 'sum_of_my_quality' => 'scores.data_quality'],
     *     'avg' => ['average_performance' => 'scores.performance', 'scores.data_quality'],
     * ]
     * Result example:
     * "aggregations" =>  [
     *     named results will be like this:
     *     'performance_sum' => ['value' => val],
     *     'sum_of_my_quality' => ['value' => val],
     *     'average_performance' => ['value' => val],
     *     unnamed results:
     *     'scores.data_quality_avg' => ['value' => val],
     * ]
     *
     * @param string|array             $aggregation
     * @param string                   $type
     * @param AbstractAggregation|null $subAggregation
     * @param null| BoolQuery          $filterQuery
     *
     * @return  AbstractAggregation[]|FilterAggregation[]|HistogramAggregation[]|TermsAggregation[]
     *
     * @todo add support of
     *       https://www.elastic.co/guide/en/elasticsearch/reference/current/search-aggregations-bucket-terms-aggregation.html#_filtering_values_with_exact_values_2
     * @todo add support of
     *       https://www.elastic.co/guide/en/elasticsearch/reference/current/search-aggregations-bucket-terms-aggregation.html#_minimum_document_count_4
     */
    public function addAggregation(
        array|string $aggregation,
        string $type = self::AGGREGATION_TERMS,
        ?AbstractAggregation $subAggregation = null,
        ?BoolQuery $filterQuery = null
    ): array {
        if (!key_exists($type, self::SUPPORTED_AGGREGATIONS)) {
            throw new InvalidArgumentException(sprintf('Unsupported aggregation type "%s" specified', $type));
        }

        if (!is_array($aggregation) && $type != self::AGGREGATION_FILTER) {
            $termsAggregation = new TermsAggregation(self::TERM_AGGREGATION_PREFIX . $aggregation, $aggregation);
            $termsAggregation->addParameter('size', $this->getTermsAggregationBucketsLimit($aggregation));
            if ($subAggregation) {
                $termsAggregation->addAggregation($subAggregation);
            }

            return [$termsAggregation];
        }

        /**
         * Note: default min_doc_count is 0 for histogram aggregation to fill gaps in the histogram with empty buckets
         */
        if ($type == self::AGGREGATION_HISTOGRAM) {

            $aggregationInstance = $this->createHistogramAggregation($aggregation, $type, $subAggregation);

            return [$aggregationInstance];
        }

        if ($type == self::AGGREGATION_FILTER) {
            $filterAggregation = new FilterAggregation($aggregation, $filterQuery);
            if ($subAggregation) {
                $filterAggregation->addAggregation($subAggregation);
            }

            return [$filterAggregation];
        }

        /**
         * Note: default min_doc_count is 1 for all terms/metric aggregations
         */
        $aggs = [];
        foreach ($aggregation as $name => $field) {
            $aggregationName = $field;
            if (!is_numeric($name)) {
                $aggregationName = $name;
            }
            $class = self::SUPPORTED_AGGREGATIONS[$type];
            /** @var AbstractAggregation $aggregationInstance */
            $aggregationInstance = new $class($aggregationName);
            $aggregationInstance->setField($field);
            if ($subAggregation) {
                $aggregationInstance->addAggregation($subAggregation);
            }

            $aggs[] = $aggregationInstance;
        }

        return $aggs;
    }

    /**
     * @param array                         $aggregation
     * @param string                   $type
     * @param AbstractAggregation|null $subAggregation
     *
     * @return HistogramAggregation|null
     */
    public function createHistogramAggregation(
        array $aggregation,
        string $type,
        ?AbstractAggregation $subAggregation = null
    ): HistogramAggregation|null {
        $aggregationInstance = null;
        foreach ($aggregation as $field => $interval) {
            $aggregationName = $field;
            if (is_numeric($field)) {
                $field = $interval;
                $interval = self::AGGREGATION_HISTOGRAM_DEFAULT_INTERVAL;
            }
            $class = self::SUPPORTED_AGGREGATIONS[$type];
            /** @var HistogramAggregation $aggregationInstance */
            $aggregationInstance = new $class($aggregationName);
            $aggregationInstance->setField($field);
            $aggregationInstance->setInterval($interval);
            $aggregationInstance->setMinDocCount(1);
            if ($subAggregation) {
                $aggregationInstance->addAggregation($subAggregation);
            }
        }

        return $aggregationInstance;
    }
}