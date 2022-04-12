<?php

namespace Levtechdev\Simpaas\Database\Elasticsearch\Builder;

use Elasticsearch\Common\Exceptions\InvalidArgumentException;
use Levtechdev\Simpaas\Database\SearchCriteria;
use ONGR\ElasticsearchDSL\Aggregation\AbstractAggregation;
use ONGR\ElasticsearchDSL\Aggregation\Bucketing\FilterAggregation;
use ONGR\ElasticsearchDSL\Aggregation\Bucketing\NestedAggregation;
use ONGR\ElasticsearchDSL\Aggregation\Bucketing\ReverseNestedAggregation;
use ONGR\ElasticsearchDSL\Aggregation\Bucketing\TermsAggregation;
use ONGR\ElasticsearchDSL\BuilderInterface;
use ONGR\ElasticsearchDSL\Query\Compound\BoolQuery;
use ONGR\ElasticsearchDSL\Query\Joining\NestedQuery;
use ONGR\ElasticsearchDSL\Query\MatchAllQuery;
use ONGR\ElasticsearchDSL\Search;
use ONGR\ElasticsearchDSL\Sort\FieldSort;

class NestedBuilder
{
    const DEFAULT_PAGINATION_LIMIT = 10;

    const SOURCE_INCLUDES = 'includes';
    const SOURCE_EXCLUDES = 'excludes';

    /** @var QueryBuilder */
    protected QueryBuilder $baseQueryBuilder;

    /** @var array */
    protected array $searchCriteria;
    protected string $nestedPath;

    // for example: compound, preview searches
    protected $additionalSearchQuery;

    /** @var BoolQuery */
    protected BoolQuery $nestedQuery;
    /** @var BoolQuery */
    protected BoolQuery $rootQuery;

    protected $rootSortQuery;
    protected $rootSourcedQuery;
    protected array $arrayAggregationsQueries = [];

    /** @var int */
    protected int $size;
    /** @var int */
    protected int $from;

    /** @var Search */
    protected Search $search;

    /** @var array */
    protected array $nestedInnerHits;

    /** @var array  */
    protected array $aggregationBucketsLimits = [];

    /**
     * NestedBuilder constructor.
     *
     * @param array  $searchCriteria
     * @param string $nestedPath
     */
    public function __construct(array $searchCriteria, string $nestedPath = 'variation')
    {
        $this->searchCriteria = $searchCriteria;
        $this->nestedPath = $nestedPath;

        $this->nestedQuery = $this->getDefaultNestedQuery();
        $this->search = new Search();
        $this->baseQueryBuilder = new QueryBuilder([]);
    }

    /**
     * @param array $aggregationsBucketsLimits
     *
     * @return $this
     */
    public function setAggregationBucketsLimits(array $aggregationsBucketsLimits): self
    {
        $this->aggregationBucketsLimits = $aggregationsBucketsLimits;

        return $this;
    }

    /**
     * @param string $aggregationName
     *
     * @return int
     */
    protected function getAggregationBucketsLimit(string $aggregationName): int
    {
        return $this->aggregationBucketsLimits[$aggregationName] ?? QueryBuilder::DEFAULT_TERMS_AGGREGATION_BUCKETS_LIMIT;
    }

    /**
     * @param array $nestedInnerHists
     *
     * @return $this
     */
    public function setNestedInnerHists(array $nestedInnerHists): self
    {
        $this->nestedInnerHits = $nestedInnerHists;

        return $this;
    }

    /**
     * @param string $filterSwitch
     *
     * @return array
     */
    public function build(string $filterSwitch = BoolQuery::MUST): array
    {
        $filterSwitch = $this->getBoolQueryType($filterSwitch);

        $this->buildSearchQuery();
        $this->buildSort();
        $this->buildSource();
        $this->buildAggregations();
        $this->buildPagination();

        return $this->mergeSearchQueries($filterSwitch);
    }

    /**
     * @param string $filterSwitch
     *
     * @return array
     */
    protected function mergeSearchQueries(string $filterSwitch): array
    {
        if (!empty($this->rootQuery)) {
            $this->search->addQuery($this->rootQuery, $filterSwitch);
        }

        /** @var NestedQuery $nestedQuery */
        $nestedQuery = $this->getWrappedNestedQuery($this->nestedQuery);

        if (!empty($this->arrayAggregationsQueries)) {
            foreach ($this->arrayAggregationsQueries as $aggregationQuery) {
                $this->search->addAggregation($aggregationQuery);
            }
        }

        if (!empty($this->rootSortQuery)) {
            foreach ($this->rootSortQuery as $sortQuery) {
                $this->search->addSort($sortQuery);
            }
        }

        $this->search->addQuery($nestedQuery, $filterSwitch);

        if (!empty($this->searchCriteria[QueryBuilder::POST_FILTER])) {
            $this->search->addPostFilter($this->buildPostFilter($this->searchCriteria[QueryBuilder::POST_FILTER]));
        }

        if (!empty($this->additionalSearchQuery)) {
            $this->search->addQuery($this->additionalSearchQuery);
        }

        $this->search->setSize($this->size);
        $this->search->setFrom($this->from);

        $query = $this->search->toArray();
        // todo need refactor (use Object)
        if (!empty($this->rootSourcedQuery)) {
            $query['_source'] = $this->rootSourcedQuery;
        }

        return $query;
    }

    /**
     * @return $this
     */
    protected function buildPagination(): self
    {
        $searchCriteriaPagination = $this->getSearchCriteriaPagination();

        $this->from = $this->searchCriteria[SearchCriteria::FROM] ?? ($searchCriteriaPagination['page'] - 1) * $searchCriteriaPagination['limit'];
        $this->size = $this->searchCriteria[SearchCriteria::SIZE] ?? $searchCriteriaPagination['limit'];

        return $this;
    }

    /**
     * @return $this
     */
    protected function buildAggregations(): self
    {
        // We defined default aggregation, but we unset aggr for multisearch
        // So ignore post filter for aggregation in some case
        if (empty($this->searchCriteria[QueryBuilder::AGGREGATION])) {

            return $this;
        }

        $postFilterFields = $this->getPostFilterFields();
        $mergedAggregations = $this->getMergedAggregations($postFilterFields);

        foreach ($mergedAggregations as $type => $aggregation) {
            $typeAggregation = is_numeric($type) ? QueryBuilder::AGGREGATION_TERMS : $type;
            $aggregationQuery = $this->prepareAggregation($aggregation, $typeAggregation);

            $arrayAggr = [];

            if (is_array($aggregationQuery)) {
                foreach($aggregationQuery as $aggregationInstance) {
                    if ($this->isNestedField($aggregationInstance->getField())) {
                        $arrayAggr[] = $this->getWrappedNestedAggregationQuery($aggregationQuery->getName(),
                            $aggregationQuery);

                        continue;
                    }
                    $arrayAggr[] = $aggregationInstance;
                }
             $aggregationQuery = $arrayAggr;

            } else {
                if ($this->isNestedField($aggregationQuery->getField())) {
                    $aggregationQuery = $this->getWrappedNestedAggregationQuery($aggregationQuery->getName(),
                        $aggregationQuery);
                }
            }

            // todo to need reimplement if $aggregationQuery is array
            if (!empty($postFilterFields)) {
                $searchCriteria = $this->excludeFieldFromCriteria(
                    $this->searchCriteria[QueryBuilder::POST_FILTER],
                    $this->getAggrFieldName($aggregation));

               if (!empty($searchCriteria)) {
                   $filterAggregationQuery = new FilterAggregation(
                       $aggregationQuery->getName(),
                       $this->buildPostFilter($searchCriteria)
                   );

                   $filterAggregationQuery->addAggregation($aggregationQuery);
                   $this->arrayAggregationsQueries[] = $filterAggregationQuery;

                   continue;
               }
            }

            if (is_array($aggregationQuery)) {
                $this->arrayAggregationsQueries = array_merge($this->arrayAggregationsQueries, $aggregationQuery);
            } else {
                $this->arrayAggregationsQueries[] =  $aggregationQuery;
            }

        }

        return $this;
    }

    /**
     * @param string|array   $aggregation
     * @param string         $type
     * @param BoolQuery|null $filterQuery
     *
     * @return AbstractAggregation|array
     */
    protected function prepareAggregation(
        array|string $aggregation,
        string $type = QueryBuilder::AGGREGATION_TERMS,
        ?BoolQuery $filterQuery = null
    ): AbstractAggregation|array {
        if (!key_exists($type, QueryBuilder::SUPPORTED_AGGREGATIONS)) {
            throw new InvalidArgumentException(sprintf('Unsupported aggregation type specified: "%s"', $type));
        }

        if (!is_array($aggregation) && $type != QueryBuilder::AGGREGATION_FILTER) {
            $termsAggregation = new TermsAggregation(
                QueryBuilder::TERM_AGGREGATION_PREFIX . $aggregation,
                $aggregation
            );
            $termsAggregation->addParameter('size', $this->getAggregationBucketsLimit($aggregation));
            // Sort size related aggregations by bucket keys alphabetically
            if (stripos($aggregation, 'size') !== false) {
                $termsAggregation->addParameter('order', ['_key' => 'asc']);
            }

            return $termsAggregation;
        }

        /**
         * Note: default min_doc_count is 0 for histogram aggregation to fill gaps in the histogram with empty buckets
         */
        if ($type == QueryBuilder::AGGREGATION_HISTOGRAM) {
            return $this->baseQueryBuilder->createHistogramAggregation($aggregation, $type);
        }

        if ($type == QueryBuilder::AGGREGATION_FILTER && !empty($filterQuery)) {
            return new FilterAggregation($aggregation, $filterQuery);
        }

        $aggregations = [];
        /**
         * Note: default min_doc_count is 1 for all terms/metric aggregations
         */
        foreach ($aggregation as $name => $field) {
            $aggregationName = $field;
            if (!is_numeric($name)) {
                $aggregationName = $name;
            }
            $class = QueryBuilder::SUPPORTED_AGGREGATIONS[$type];
            /** @var AbstractAggregation $aggregationInstance */
            $aggregationInstance = new $class($aggregationName);
            $aggregationInstance->setField($field);
            $aggregations[] = $aggregationInstance;
        }

        return $aggregations;
    }

    /**
     * @return $this
     */
    protected function buildSource(): self
    {
        $fields = $this->getSearchCriteriaFields();

        $excludes = $this->searchCriteria[QueryBuilder::EXCLUDE_FIELDS] ?? [];
        // need to exclude variation from main _source if nestedInnerHists presents
        if (!empty($this->nestedInnerHits)) {
            $excludes = [$this->nestedPath];
        }

        // we need adding nested_field.id(this is required for nested object building on Resource layer) only if $fields contain any nested_fields.
        $nestedFields = array_filter($fields, function($item){
           return str_starts_with($item, $this->nestedPath);
        });
        if (count($nestedFields)) {
            $fields[] = $this->nestedPath . '.id';
        }

        list($includes, $excludes) = array_values($this->baseQueryBuilder->prepareFields(array_unique($fields),
            $excludes));

        if (empty($this->nestedInnerHits)) {
            if (!empty($includes[self::SOURCE_INCLUDES])) {
                $this->rootSourcedQuery[self::SOURCE_INCLUDES] = $includes[self::SOURCE_INCLUDES];
            }

            if (!empty($excludes[self::SOURCE_EXCLUDES])) {
                $this->rootSourcedQuery[self::SOURCE_EXCLUDES] = $excludes[self::SOURCE_EXCLUDES];
            }
        } else {
            $rootIncludes = $this->getFieldsByType($includes[self::SOURCE_INCLUDES]);
            $rootExcludes = $this->getFieldsByType($excludes[self::SOURCE_EXCLUDES]);

            $nestedIncludes = $this->getFieldsByType($includes[self::SOURCE_INCLUDES], true);
            $nestedExcludes = $this->getFieldsByType($excludes[self::SOURCE_EXCLUDES], true);

            if (!empty($rootIncludes)) {
                $this->rootSourcedQuery[self::SOURCE_INCLUDES] = array_values($rootIncludes);
            }

            if (!empty($rootExcludes)) {
                $this->rootSourcedQuery[self::SOURCE_EXCLUDES] = array_values($rootExcludes);
            }

            if (!empty($nestedIncludes)) {
                $this->nestedInnerHits['_source'][self::SOURCE_INCLUDES] = array_values($nestedIncludes);
            }

            if (!empty($nestedExcludes)) {
                $this->nestedInnerHits['_source'][self::SOURCE_EXCLUDES] = array_values($nestedExcludes);
            }
        }

        return $this;
    }

    /**
     * @param array $fields
     * @param bool $wantNested
     *
     * @return array
     */
    protected function getFieldsByType(array $fields, bool $wantNested = false): array
    {
        return array_filter($fields, function ($field) use ($wantNested) {
            if ($wantNested) {
                return $this->isNestedField($field);
            }

            return !$this->isNestedField($field);
        });
    }

    /**
     * @return $this
     */
    protected function buildSearchQuery(): self
    {
        $searchFilter = $this->getSearchCriteriaFilter();
        if (!empty($searchFilter)) {
            $this->rootQuery = $this->baseQueryBuilder->prepareFilterQuery($searchFilter);
        }

        $nestedFilter = $this->getSearchCriteriaFilter(SearchCriteria::FILTER, true);
        if (!empty($nestedFilter)) {
            $this->nestedQuery = $this->baseQueryBuilder->prepareFilterQuery($nestedFilter);
        }

        return $this;
    }

    /**
     * @return $this
     */
    protected function buildSort(): self
    {
        $rootSearchCriteriaSort = $this->getSearchCriteriaRootSort();
        if (!empty($rootSearchCriteriaSort)) {
            $this->rootSortQuery = $this->baseQueryBuilder->prepareSortQuery($rootSearchCriteriaSort);

            /** @var FieldSort $sort */
            foreach ($this->rootSortQuery as $sort) {

                if ($this->isNestedField($sort->getField())) {
                    $sort->addParameter('nested', ['path' => $this->nestedPath]);
                }
            }
        }

        return $this;
    }

    /**
     * @param string $fieldName
     *
     * @return bool
     */
    protected function isNestedField(string $fieldName): bool
    {
        if (empty($this->nestedPath)) {

            return false;
        }

        return $fieldName !== $this->nestedPath && str_starts_with($fieldName, $this->nestedPath);
    }

    /**
     * @return array
     */
    protected function getSearchCriteriaPagination(): array
    {
        if (empty($this->searchCriteria[SearchCriteria::PAGINATION])) {

            return [
                'limit' => self::DEFAULT_PAGINATION_LIMIT,
                'page'  => 0
            ];
        }

        return $this->searchCriteria[SearchCriteria::PAGINATION];
    }

    /**
     * @return array
     */
    protected function getSearchCriteriaFields(): array
    {
        if (empty($this->searchCriteria[SearchCriteria::DATA_FIELDS])) {

            return [];
        }

        return $this->searchCriteria[SearchCriteria::DATA_FIELDS];
    }

    /**
     * @return array
     */
    protected function getSearchCriteriaRootSort(): array
    {
        if (empty($this->searchCriteria[SearchCriteria::SORT])) {

            return [];
        }

        return $this->searchCriteria[SearchCriteria::SORT];
    }

    /**
     * @param string $typeFilter
     * @param bool   $wantNestedFilter
     *
     * @return array
     */
    protected function getSearchCriteriaFilter(
        string $typeFilter = SearchCriteria::FILTER,
        bool $wantNestedFilter = false
    ): array {
        if (empty($this->searchCriteria[$typeFilter])) {

            return [];
        }

        return array_filter($this->searchCriteria[$typeFilter], function ($items) use ($wantNestedFilter) {
            if (empty($items['group'][0]['field'])) {

                return false;
            }

            if ($wantNestedFilter) {
                return $this->isNestedField($items['group'][0]['field']);
            }

            return !$this->isNestedField($items['group'][0]['field']);
        });
    }

    /**
     * @param array $searchCriteria
     * @param bool  $wantNestedFilter
     *
     * @return array
     */
    protected function getSearchCriteriaFilterByType(array $searchCriteria, bool $wantNestedFilter = false): array
    {
        return array_filter($searchCriteria, function ($items) use ($wantNestedFilter) {
            $key = key($items['group']);
            if ($wantNestedFilter) {
                return $this->isNestedField($items['group'][$key]['field']);
            }

            return !$this->isNestedField($items['group'][$key]['field']);
        });
    }

    /**
     * @param array $postFilterFields
     *
     * @return array|mixed
     */
    protected function getMergedAggregations(array $postFilterFields): mixed
    {
        // We use multisearch Data and Aggregation. It is detetected by Collection via unset searchCriteria aggregations
        if (empty($postFilterFields)) {

            return $this->searchCriteria[QueryBuilder::AGGREGATION];
        }

        $postFilterAggregation = [];
        $searchCriteriaAggregations = $this->searchCriteria[QueryBuilder::AGGREGATION] ?? [];

        foreach ($postFilterFields as $field) {
            foreach ($searchCriteriaAggregations as $key => $aggregation) {
                if ($field === $this->getAggrFieldName($aggregation)) {
                    continue 2;
                }
            }
            $postFilterAggregation[] = $field;
        }

        return array_merge($this->searchCriteria[QueryBuilder::AGGREGATION], $postFilterAggregation);
    }

    /**
     * @param $aggregation
     *
     * @return int|string|null
     */
    protected function getAggrFieldName(array|string $aggregation): string|int|null
    {
        if (is_string($aggregation)) {
            return $aggregation;
        }

        return key($aggregation);
    }

    /**
     * @return array
     */
    protected function getPostFilterFields(): array
    {
        $postFilterFields = [];
        if (!empty($this->searchCriteria[QueryBuilder::POST_FILTER])) {
            foreach ($this->searchCriteria[QueryBuilder::POST_FILTER] as $postFilter) {
                foreach ($postFilter['group'] as $filter) {
                    $postFilterFields[] = $filter['field'] ?? '';
                }
            }
        }

        return $postFilterFields;
    }

    /**
     * @param BoolQuery $query
     *
     * @return $this
     */
    public function setAdditionalSearchQuery(BoolQuery $query): self
    {
        $this->additionalSearchQuery = $query;

        return $this;
    }

    /**
     * @param BuilderInterface $nestedSearchQuery
     *
     * @return NestedQuery
     */
    protected function getWrappedNestedQuery(BuilderInterface $nestedSearchQuery): NestedQuery
    {
        $params = [];
        if (!empty($this->nestedInnerHits)) {
            $params['inner_hits'] = $this->nestedInnerHits;
        }

        return new NestedQuery($this->nestedPath, $nestedSearchQuery, $params);
    }

    /**
     * @param array $searchCriteria
     *
     * @return BoolQuery
     */
    protected function buildPostFilter(array $searchCriteria): BoolQuery
    {
        $rootSearchCriteria = $this->getSearchCriteriaFilterByType($searchCriteria);
        $rootFilter = $this->baseQueryBuilder->prepareFilterQuery($rootSearchCriteria);

        $nestedSearchCriteria = $this->getSearchCriteriaFilterByType($searchCriteria, true);
        $nestedFilter = $this->baseQueryBuilder->prepareFilterQuery($nestedSearchCriteria);

        $filter = new BoolQuery();
        if (!empty($nestedFilter->getQueries())) {
            $nestedFilter = $this->getWrappedNestedQuery($nestedFilter);

            $filter->add($nestedFilter);
        }

        if (!empty($rootFilter->getQueries())) {
            $filter->add($rootFilter);
        }

        return $filter;
    }

    /**
     * @param array $criteria
     * @param array|string $field
     *
     * @return array
     */
    protected function excludeFieldFromCriteria(array $criteria, array|string $field): array
    {
        $resultCriteria = $criteria;
        if (is_array($field)) {
            $field = key($field);
        }
        foreach ($resultCriteria as $i => $groups) {
            if (!empty($groups['group'])) {
                foreach ($groups['group'] as $k => $group) {
                    if (!empty($group['field']) && $group['field'] == $field) {
                        unset($resultCriteria[$i]['group'][$k]);
                    }

                    // need to exclude filter for attributes which are under the same facet item
                    if (!empty($group['field']) && $this->isAttributesUnderSameFacetItem($group['field'], $field)) {
                        unset($resultCriteria[$i]['group'][$k]);
                    }
                }

                if (empty($resultCriteria[$i]['group'])) {
                    unset($resultCriteria[$i]);
                }
            }
        }

        return $resultCriteria;
    }

    /**
     * @param string $firstAttribute
     * @param string $secondAttribute
     *
     * @return bool
     */
    protected function isAttributesUnderSameFacetItem(string $firstAttribute, string $secondAttribute): bool
    {
        if (empty($this->searchCriteria[SearchCriteria::ATTRIBUTE_VIRTUAL_FACET_NAME_MAP][$firstAttribute])
            || empty($this->searchCriteria[SearchCriteria::ATTRIBUTE_VIRTUAL_FACET_NAME_MAP][$secondAttribute])
        ) {
            return false;
        }

        return $this->searchCriteria[SearchCriteria::ATTRIBUTE_VIRTUAL_FACET_NAME_MAP][$firstAttribute]
               === $this->searchCriteria[SearchCriteria::ATTRIBUTE_VIRTUAL_FACET_NAME_MAP][$secondAttribute];
    }

    /**
     * @param string $value
     *
     * @return string
     */
    protected function getBoolQueryType(string $value): string
    {
        if (in_array($value, [BoolQuery::SHOULD, BoolQuery::FILTER, BoolQuery::MUST_NOT, BoolQuery::MUST])) {
            return $value;
        }

        if ($value === SearchCriteria::CONDITION_OR) {
            return BoolQuery::SHOULD;
        }

        if ($value === SearchCriteria::CONDITION_AND) {
            return BoolQuery::MUST;
        }

        throw new \InvalidArgumentException("Not valid argument in " . __FUNCTION__ . " given " . $value);
    }

    /**
     * @param string              $aggregationName
     * @param AbstractAggregation $aggregation
     *
     * @return array
     */
    protected function getWrappedNestedAggregationQuery(
        string $aggregationName, AbstractAggregation $aggregation): NestedAggregation
    {
        $nestedAggregation = new NestedAggregation($aggregationName, $this->nestedPath);

        $aggregation->addAggregation(new ReverseNestedAggregation('real_count'));
        $nestedAggregation->addAggregation($aggregation);

        return $nestedAggregation;
    }

    /**
     * @return BoolQuery
     */
    protected function getDefaultNestedQuery(): BoolQuery
    {
        return new BoolQuery([BoolQuery::FILTER => new MatchAllQuery()]);
    }
}