<?php
declare(strict_types=1);

namespace Levtechdev\Simpaas\Database\Mysql;

use Illuminate\Database\Query\Builder;
use Psr\Log\InvalidArgumentException;
use Levtechdev\Simpaas\Model\AbstractModel;

class QueryBuilder
{
    const AGGREGATION      = 'aggregation';
    const SCROLL           = 'scroll';
    const SCROLL_LAST_ID   = 'last_id';
    const AGGREGATIONS_KEY = 'COUNT(*)';

    /** @var Builder */
    protected Builder $queryBuilder;

    /** @var array */
    protected array $filterGroups = [];

    /** @var array */
    protected array $aggregationToSelect = [];

    /** @var array */
    protected array $preparedFilter = [];

    /** @var string */
    protected string $table;

    /**
     * QueryBuilder constructor.
     *
     * @param string $table
     */
    public function __construct(string $table)
    {
        $this->table = $table;
        $this->queryBuilder = app('db')->connection()->query();

        $this->queryBuilder->from($this->table);
    }

    /**
     * @return Builder
     */
    public function build(): Builder
    {
        return $this->queryBuilder;
    }

    /**
     * @return Builder
     */
    protected function getQueryBuilder(): Builder
    {
        return $this->queryBuilder;
    }

    /**
     * @return string
     */
    protected function getTable(): string
    {
        return $this->table;
    }

    /**
     * @param string $fieldName
     *
     * @return mixed
     */
    protected function getPreparedFieldName(string $fieldName): string
    {
        if (strpos($fieldName, '.')) {

            return $fieldName;
        }

        return sprintf('%s.%s', $this->getTable(), $fieldName);
    }

    /**
     * price                  -> product_raw.price
     * price.msrp             -> product_raw.price->msrp
     * product_raw.price      -> product_raw.price
     * product_raw.price.msrp -> product_raw.price->msrp
     *
     * @param string $fieldName
     *
     * @return string
     */
    public function getPreparedFullFieldName(string $fieldName): string
    {
        // using for defined field name as 'joined_table.field' in where condition
        if (str_starts_with($fieldName, '`')) {

            return str_replace('`', '', $fieldName);
        }

        $table = $this->getTable();
        if (str_starts_with($fieldName, $table)) {

            $fieldName = str_replace($table . '.', '', $fieldName);
        }

        if (strpos($fieldName, '.') != 0) {
            $fieldName = str_replace('.', '->', $fieldName);
        }

        return sprintf('%s.%s', $table, $fieldName);
    }

    /**
     * @param string $value
     *
     * @return string
     */
    public function adaptLikeOperator(string $value): string
    {
        return str_replace('*', '%', $value);
    }

    /**
     * @param array $filterGroups
     * @param array $columnTypes
     *
     * @return $this
     */
    public function addFilterQuery(array $filterGroups, array $columnTypes = []): static
    {
        if (empty($filterGroups)) {

            return $this;
        }

        $filterGroups = array_values($filterGroups);
        $closures = [];
        foreach ($filterGroups as $group) {
            $closures[] = $this->buildQueryByGroup($group, $columnTypes);
        }

        $this->setPreparedFilterToBuilder($closures);

        return $this;
    }

    /**
     * @param array $selectColumns
     *
     * @return $this
     */
    public function addSelectRaw(array $selectColumns): static
    {
        if (empty($selectColumns)) {

            return $this;
        }

        foreach ($selectColumns as $column) {
            $this->getQueryBuilder()->selectRaw($column['expression'], $column['bindings']);
        }

        return $this;
    }

    /**
     * @param array  $selectFields
     * @param string $indexField
     *
     * @return $this
     */
    public function addSelect(array $selectFields, string $indexField = AbstractModel::ID_FIELD_NAME): static
    {
        $defaultSelectedField = $this->getPreparedFieldName('*');
        if (empty($selectFields)) {

            $this->getQueryBuilder()->select([$defaultSelectedField]);

            return $this;
        }

        $preparedSelectedFields = array_map([$this, 'getPreparedFieldName'], $selectFields);
        $shouldSetDefaultSelectedField = true;
        foreach ($preparedSelectedFields as $selectField) {
            // @todo strpos() can be converted into str_contains()
            if (is_string($selectField) && strpos($selectField, $this->getTable(), 0) !== false) {
                $shouldSetDefaultSelectedField = false;

                break;
            }
        }

        if ($shouldSetDefaultSelectedField) {
            array_unshift($preparedSelectedFields, $defaultSelectedField);
        } else {
            $preparedSelectedFields[] = $this->getPreparedFieldName($indexField);
        }

        $this->getQueryBuilder()->select($preparedSelectedFields);

        return $this;
    }

    /**
     * @param array $orders
     *
     * @return $this
     */
    public function addOrders(array $orders): static
    {
        if (empty($orders)) {

            return $this;
        }

        foreach ($orders as $field => $direction) {
            $this->getQueryBuilder()->orderBy($this->getPreparedFieldName($field), $direction);
        }

        return $this;
    }

    /**
     * @param array $froms
     *
     * @return $this
     */
    public function addFromRaw(array $froms): static
    {
        if (empty($froms)) {

            return $this;
        }

        foreach ($froms as $from) {
            $this->getQueryBuilder()->fromRaw(
                $from['expression'],
                $from['bindings']
            );
        }

        return $this;
    }

    /**
     * @param array $joins
     *
     * @return $this
     */
    public function addJoins(array $joins): static
    {
        if (empty($joins)) {

            return $this;
        }

        foreach ($joins as $join) {
            $this->getQueryBuilder()->join(
                $join['table'],
                $join['first'],
                $join['operator'],
                $join['second'],
                $join['type'],
                $join['where']
            );
        }

        return $this;
    }

    /**
     * @param string|null ...$groupsBy
     *
     * @return $this
     */
    public function addGroupsBy(...$groupsBy): static
    {
        if (empty($groupsBy)) {

            return $this;
        }

        $this->getQueryBuilder()->groupBy(...array_map([$this, 'getPreparedFieldName'], $groupsBy));

        return $this;
    }

    /**
     * @param array $distinct
     *
     * @return $this
     */
    public function addDistinct(array $distinct): static
    {
        if (empty($distinct)) {

            return $this;
        }

        $this->getQueryBuilder()->distinct($distinct);

        return $this;
    }

    /**
     * @param array $where
     *
     * @return $this
     */
    public function addWhere(array $where): static
    {
        if (empty($where)) {

            return $this;
        }

        foreach ($where as $item) {
            $column = $item['column'];
            if (!($item['column'] instanceof \Closure)) {
                $column = $this->getPreparedFieldName($column);
            }

            $this->getQueryBuilder()->where(
                $column,
                $item['operator'],
                $item['value']
            );
        }

        return $this;
    }

    /**
     * @param array $whereIn
     *
     * @return $this
     */
    public function addWhereIn(array $whereIn): static
    {
        if (empty($whereIn)) {

            return $this;
        }

        foreach ($whereIn as $item) {
            $this->getQueryBuilder()->whereIn(
                $this->getPreparedFieldName($item['column']),
                $item['values'],
                $item['boolean'],
                $item['not']
            );
        }

        return $this;
    }

    /**
     * @param array  $whereNull
     * @param string $condition
     *
     * @return $this
     */
    public function addWhereNull(array $whereNull, $condition = SearchCriteria::CONDITION_AND): self
    {
        if (empty($whereNull)) {

            return $this;
        }

        $this->getQueryBuilder()->whereNull(array_map([$this, 'getPreparedFieldName'], $whereNull), $condition);

        return $this;
    }

    /**
     * @param array $orWhere
     *
     * @return $this
     */
    public function addOrWhere(array $orWhere): self
    {
        if (empty($orWhere)) {

            return $this;
        }

        foreach ($orWhere as $item) {
            $this->getQueryBuilder()->orWhere(
                $this->getPreparedFieldName($item['column']),
                $item['operator'],
                $item['value']
            );
        }

        return $this;
    }

    /**
     * @param int $lastId
     *
     * @return $this
     */
    public function addScrollLastId(int $lastId): self
    {
        if ($lastId === -1) {

            return $this;
        }

        $this->queryBuilder->where($this->getPreparedFieldName(AbstractModel::ID_FIELD_NAME), '>', $lastId);

        return $this;
    }

    /**
     * @param ?int $limit
     * @param int $offset
     *
     * @return $this
     */
    public function addLimit(?int $limit, int $offset = 0): self
    {
        if ($limit == null) {

            return $this;
        }

        $this->getQueryBuilder()->limit($limit)->offset($offset);

        return $this;
    }

    /**
     * @param array $closures
     *
     * @return $this
     */
    protected function setPreparedFilterToBuilder(array $closures)
    {
        if (empty($closures)) {

            return $this;
        }

        $this->setPreparedFilter($closures);

        $queryBuilder = $this->getQueryBuilder();
        $baseQuery = array_shift($closures);
        if (!empty($baseQuery)) {
            $queryBuilder->where($baseQuery);
        }

        foreach ($closures as $subQuery) {
            $queryBuilder->where($subQuery);
        }

        return $this;
    }

    /**
     * @param array $group
     * @param array $columnTypes
     *
     * @return \Closure
     */
    protected function buildQueryByGroup($group, $columnTypes = [])
    {
        return function ($queryBuilder) use ($group, $columnTypes) {
            /** @var \Illuminate\Database\Query\Builder $queryBuilder */

            $condition = SearchCriteria::CONDITION_AND;
            if (!empty($group['condition']) && strtolower(trim($group['condition'])) === SearchCriteria::CONDITION_OR) {
                $condition = SearchCriteria::CONDITION_OR;
            }

            foreach ($group['group'] as $filter) {
                if (!isset($filter['operator'])
                    || !isset($filter['field'])
                    || (
                        !array_key_exists('value', $filter)
                        && !in_array($filter['operator'], [
                            SearchCriteria::NOT_EXISTS, SearchCriteria::EXISTS, SearchCriteria::NOT_NULL
                        ])
                    )
                ) {
                    continue;
                }

                $filter['field'] = $this->getPreparedFullFieldName(strtolower($filter['field']));
                list($table, $column) = explode('.', str_replace('->', '.', $filter['field']));
                $fieldType = $columnTypes[$column] ?? null;

                switch ($filter['operator']) {
                    case SearchCriteria::EQ:
                        if ($filter['value'] === null) {
                            $queryBuilder->whereNull($filter['field'], $condition);

                            break;
                        }

                        if ($fieldType === MysqlResourceModel::JSON_FIELD_TYPE) {
                            $queryBuilder->whereJsonContains($filter['field'], $filter['value'], $condition);

                            break;
                        }

                        $queryBuilder->where($filter['field'], '=', $filter['value'], $condition);

                        break;
                    case SearchCriteria::IN:
                    case SearchCriteria::NOT_IN:
                        $isNotIn = $filter['operator'] === SearchCriteria::NOT_IN;

                        $values = is_array($filter['value']) ? $filter['value'] : [$filter['value']];
                        $values = array_values(array_unique($values));
                        if (empty($values)) {

                            break;
                        }

                        if ($fieldType === MysqlResourceModel::JSON_FIELD_TYPE) {
                            $innerCondition = $isNotIn ? SearchCriteria::CONDITION_AND : SearchCriteria::CONDITION_OR;
                            $queryBuilder->where(function ($queryBuilder) use ($innerCondition, $filter, $isNotIn) {
                                /** @var \Illuminate\Database\Query\Builder $queryBuilder */
                                foreach ($filter['value'] as $item) {
                                    $queryBuilder->whereJsonContains(
                                        $filter['field'], $item, $innerCondition, $isNotIn
                                    );
                                }

                            }, null, null, $condition);

                            break;
                        }

                        $queryBuilder->whereIn($filter['field'], $values, $condition, $isNotIn);

                        break;
                    case SearchCriteria::NEQ:
                        if ($fieldType === MysqlResourceModel::JSON_FIELD_TYPE) {
                            $queryBuilder->whereJsonDoesntContain($filter['field'], $filter['value'], $condition);

                            break;
                        }

                        $queryBuilder->where($filter['field'], '!=', $filter['value'], $condition);

                        break;
                    case SearchCriteria::BETWEEN:
                        $from = $filter['value']['from'] ?? null;
                        $to = $filter['value']['to'] ?? null;
                        if ($from === null || $to === null) {
                            throw new InvalidArgumentException('Invalid parameters: "from", "to"');
                        }
                        $queryBuilder->whereBetween($filter['field'], [$from, $to], $condition);

                        break;
                    case SearchCriteria::EXISTS:
                    case SearchCriteria::NOT_EXISTS:
                        $queryBuilder->where(
                            $filter['field'],
                            $this->getPdoOperator($filter['operator']),
                            null,
                            $condition
                        );

                        break;
                    case SearchCriteria::NOT_NULL:
                        $queryBuilder->whereNotNull($filter['field'], $condition);

                        break;
                    case SearchCriteria::LIKE:
                    case SearchCriteria::NOT_LIKE:
                        $queryBuilder->where(
                            $filter['field'],
                            $this->getPdoOperator($filter['operator']),
                            $this->adaptLikeOperator($filter['value']),
                            $condition
                        );

                        break;
                    case SearchCriteria::REGEXP:
                    case SearchCriteria::NOT_REGEXP:
                    case SearchCriteria::LT:
                    case SearchCriteria::GT:
                    case SearchCriteria::LTE:
                    case SearchCriteria::GTE:
                        $queryBuilder->where(
                            $filter['field'],
                            $this->getPdoOperator($filter['operator']),
                            $filter['value'],
                            $condition
                        );

                        break;
                    default:
                        throw new InvalidArgumentException(sprintf(
                            'Specified unsupported operator "%s" in search filter conditions', $filter['operator']
                        ));
                }
            }

            return $queryBuilder;
        };
    }

    /**
     * @todo need implementing all aggregation functions
     *
     * @return array
     */
    public function getAggregationToSelect(): array
    {
        return $this->aggregationToSelect;
    }

    /**
     * @todo need implementing all aggregation functions
     *
     * @param bool $prettyOutput
     *
     * @return array
     */
    public function processAggregations($prettyOutput = true)
    {
        $data = [];
        foreach ($this->getAggregationToSelect() as $item) {
            $queryBuilder = $this->getQueryBuilder()->cloneWithout(['orders', 'groups', 'columns', 'offset', 'limit']);

            $data[$item] = $queryBuilder
                ->selectRaw(sprintf('%s, COUNT(*)', $item))
                ->groupBy($item)
                ->orderBy($item)
                ->get();
        }

        return $prettyOutput ? $this->prepareAggregations($data) : $data;
    }

    /**
     * @todo need implementing all aggregation functions
     *
     * @param array $processAggregations
     *
     * @return array
     */
    protected function prepareAggregations(array $processAggregations)
    {
        if (empty($processAggregations) || !is_array($processAggregations)) {

            return [];
        }

        $data = [];
        foreach ($processAggregations as $column => $processAggregation) {
            foreach ($processAggregation as $value) {
                $data[$column][$value->{$column}] = $value->{self::AGGREGATIONS_KEY};
            }
        }

        return $data;
    }

    /**
     * @param $operator
     *
     * @return string
     */
    protected function getPdoOperator($operator): string
    {
        return SearchCriteria::OPERATORS_PDO_MAP[$operator] ?? $operator;
    }

    /**
     * @return array
     */
    public function getPreparedFilter(): array
    {
        return $this->preparedFilter;
    }

    /**
     * @param array $preparedFilter
     */
    public function setPreparedFilter(array $preparedFilter): void
    {
        $this->preparedFilter = $preparedFilter;
    }
}
