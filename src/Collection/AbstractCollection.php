<?php
declare(strict_types=1);

namespace Levtechdev\Simpaas\Collection;

use Levtechdev\Simpaas\Database\DbAdapterInterface;
use Levtechdev\Simpaas\Model\AbstractModel as AbstractModel;
use Levtechdev\Simpaas\Database\SearchCriteria;

use ArrayAccess;
use ArrayIterator;
use Countable;
use IteratorAggregate;
use Levtechdev\Simpaas\ResourceModel\AbstractResourceModel;
use Traversable;

abstract class AbstractCollection implements IteratorAggregate, Countable, ArrayAccess
{
    protected array $items = [];

    /** @var array|null */
    protected ?array $data = null;

    private bool $isLoaded = false;

    protected array $filterGroups   = [];
    private string  $filterLogic    = SearchCriteria::CONDITION_AND;
    protected array $fieldsToSelect = [];
    protected int   $limit          = -1;
    protected int   $page           = SearchCriteria::DEFAULT_PAGE;
    protected array $orders         = [];

    protected int $itemsCount = 0;
    protected int $pagesCount = 0;

    /** @var string */
    protected string $indexField = AbstractModel::ID_FIELD_NAME;

    /**
     * Additional collection flags
     *
     * @var array
     */
    protected array $flags = [];

    /** @var AbstractModel  */
    protected AbstractModel $model;

    /**
     * AbstractCollection constructor.
     *
     * @param AbstractModel   $model
     * @param AbstractModel[] $items
     * @param string          $indexField
     */
    public function __construct(AbstractModel $model, array $items = [], string $indexField = AbstractModel::ID_FIELD_NAME)
    {
        $this->setModel($model);
        $this->setIndexField($indexField);

        $this->items = $items;
    }

    /**
     * @param array  $items
     * @param string $indexField
     *
     * @return AbstractCollection
     */
    public function factoryCreate(array $items = [], string $indexField = AbstractModel::ID_FIELD_NAME): static
    {
        $collection = $this->initCollection($indexField);

        if (empty($items)) {

            return $collection;
        }

        foreach ($items as $item) {
            if (!$item instanceof AbstractModel) {
                /** @var AbstractModel $item */
                $item = $this->model->factoryCreate($item);
                $item->setHasDataChanges(false);
            }

            $collection->addItem($item);
        }

        return $collection;
    }

    /**
     * @return DbAdapterInterface
     */
    public function getAdapter(): DbAdapterInterface
    {
        return $this->getModel()->getResource()->getAdapter();
    }


    /**
     * @param string $indexField
     *
     * @return $this
     */
    protected function initCollection(string $indexField = AbstractModel::ID_FIELD_NAME): static
    {
        return new static($this->model, [], $indexField);
    }

    /**
     * @return bool
     */
    public function isLoaded(): bool
    {
        return $this->isLoaded;
    }

    /**
     * @return $this
     */
    public function clear(): self
    {
        $this->indexField = AbstractModel::ID_FIELD_NAME;
        $this->items = [];
        $this->data = null;
        $this->isLoaded = false;

        $this->filterGroups = [];
        $this->filterLogic = SearchCriteria::CONDITION_AND;
        $this->fieldsToSelect = [];

        $this->limit = -1;

        $this->page = SearchCriteria::DEFAULT_PAGE;
        $this->orders = [];

        $this->itemsCount = 0;
        $this->pagesCount = 0;
        $this->flags = [];

        return $this;
    }

    /**
     * @param string $indexField
     *
     * @return $this
     */
    protected function setIndexField(string $indexField): self
    {
        $this->indexField = $indexField;

        return $this;
    }

    /**
     * @return string
     */
    protected function getIndexField(): string
    {
        return $this->indexField;
    }

    /**
     * @return AbstractResourceModel
     */
    public function getResourceModel(): AbstractResourceModel
    {
        return $this->getModel()->getResource();
    }

    /**
     * @return ArrayIterator|Traversable
     */
    public function getIterator(): ArrayIterator|Traversable
    {
        // This was removed because for Misha it is not straightforward and he is making to many errors when coding
        // $this->load();

        return new ArrayIterator($this->items);
    }

    /**
     * Get the items stored in the collection
     */
    public function getItems(): array
    {
        $this->load();

        return $this->items;
    }

    /**
     * @return mixed|null
     */
    public function getLastItem(): AbstractModel|null
    {
        if (empty($this->items)) {

            return null;
        }

        return end($this->items);
    }

    /**
     * Reset the collection (implementation required by Iterator Interface)
     */
    public function rewind()
    {
        reset($this->items);
    }

    /**
     * Get the current item in the collection (implementation required by Iterator Interface)
     */
    public function current()
    {
        return current($this->items);
    }

    /**
     * Move to the next item in the collection (implementation required by Iterator Interface)
     */
    public function next()
    {
        next($this->items);
    }

    /**
     * @return int|string|null
     */
    public function key()
    {
        return key($this->items);
    }

    /**
     * Get the key of the current item in the collection (implementation required by Iterator Interface)
     */
    public function getId(): string|int
    {
        return $this->key();
    }

    /**
     * Check if there are more items in the collection (implementation required by Iterator Interface)
     */
    public function valid(): bool
    {
        return (boolean)$this->current();
    }

    /**
     * Count the number of items in the collection (implementation required by Countable Interface)
     */
    public function count():int
    {
        return count($this->items);
    }

    /**
     * Add a item to the collection (implementation required by ArrayAccess interface)
     */
    public function offsetSet($id, $item): void
    {
        $this->setIsLoaded(true);

        if ($id === null) {
            if (!in_array($item->getData($this->getIndexField()), $this->items, true)) {
                $this->items[] = $item;

                return;
            }
        }

        $this->items[$item->getData($this->getIndexField())] = $item;
    }

    /**
     * @param AbstractModel $item
     *
     * @return $this
     */
    public function addItem(AbstractModel $item): static
    {
        $index = $item->getData($this->getIndexField());

        $this->offsetSet($index, $item);

        return $this;
    }

    /**
     * Remove a item from the collection (implementation required by ArrayAccess interface)
     *
     * @param string|AbstractModel $id
     *
     * @return $this
     */
    public function offsetUnset($id)
    {
        if ($id instanceof AbstractModel) {
            $id = $id->getData($this->getIndexField());
        }

        if (array_key_exists($id, $this->items)) {
            unset($this->items[$id]);
        }

        return $this;
    }

    /**
     * Remove a item from the collection (implementation required by ArrayAccess interface)
     *
     * @param int|string|AbstractModel $id
     *
     * @return AbstractCollection
     */
    public function removeItem(int|string|AbstractModel $id): static
    {
        return $this->offsetUnset($id);
    }

    /**
     * Get the specified item in the collection (implementation required by ArrayAccess interface)
     */
    public function offsetGet($id)
    {
        if (array_key_exists($id, $this->items)) {

            return $this->items[$id];
        }

        return null;
    }

    /**
     * Get the specified item in the collection (implementation required by ArrayAccess interface)
     *
     * @param string|int|AbstractModel $id
     *
     * @return AbstractModel|null
     */
    public function getItemById(string|int|AbstractModel $id): AbstractModel|null
    {
        return $this->offsetGet($id);
    }

    /**
     * Check if the specified item exists in the collection (implementation required by ArrayAccess interface)
     */
    public function offsetExists($id)
    {
        return array_key_exists($id, $this->items);
    }

    /**
     * Check if the specified item exists in the collection (implementation required by ArrayAccess interface)
     *
     * @param string|int $id
     *
     * @return bool
     */
    public function itemExists(string|int $id): bool
    {
        return $this->offsetExists($id);
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        $result = [];
        if (!empty($this->items)) {
            foreach ($this->items as $slug => $item) {
                $result[$slug] = $item->getData();
            }
        }

        return $result;
    }

    /**
     * @param bool $flag
     *
     * @return $this
     */
    protected function setIsLoaded(bool $flag = true): static
    {
        $this->isLoaded = $flag;

        return $this;
    }

    /**
     * @param AbstractModel $object
     */
    public function setModel(AbstractModel $object): void
    {
        $this->model = $object;
    }

    /**
     * @return AbstractModel
     */
    public function getModel(): AbstractModel
    {
        return $this->model;
    }

    /**
     * @return int
     */
    public function getSize(): int
    {
        return $this->count();
    }

    /**
     * Walk through the collection and run model method or external callback
     * with optional arguments
     *
     * Returns array with results of callback for each item
     *
     * @param callable $callback
     * @param array    $args
     *
     * @return array
     */
    public function walk(callable $callback, array $args = []):array
    {
        $results = [];
        $useItemCallback = is_string($callback) && strpos($callback, '::') === false;
        foreach ($this->getItems() as $id => $item) {
            $params = $args;
            if ($useItemCallback) {
                $cb = [$item, $callback];
            } else {
                $cb = $callback;
                array_unshift($params, $item);
            }
            $results[$id] = call_user_func_array($cb, $params);
        }

        return $results;
    }

    /**
     * Return items Ids
     * @return array
     */
    public function getIds(): array
    {
        return array_keys($this->items);
    }

    /**
     * Retrieve Flag
     *
     * @param string $flag
     *
     * @return bool|null
     */
    public function getFlag(string $flag): bool|null
    {
        return $this->flags[$flag] ?? null;
    }

    /**
     * Set Flag
     *
     * @param string    $flag
     * @param bool|null $value
     *
     * @return $this
     */
    public function setFlag(string $flag, ?bool $value = null): static
    {
        $this->flags[$flag] = $value;

        return $this;
    }

    /**
     * Run a filter over each of the items.
     *
     * @param callable|null $callback
     *
     * @return static
     */
    public function filter(callable $callback = null): static
    {
        if ($callback) {
            $items = array_filter($this->getItems(), $callback);

            return $this->factoryCreate($items);
        }

        return $this->factoryCreate(array_filter($this->getItems()));
    }

    /**
     * @return bool
     */
    public function isEmpty(): bool
    {
        return !(bool)$this->count();
    }

    /**
     * @param string $field
     * @param bool $withIds
     *
     * @return array
     */
    public function getColumnValues(string $field, bool $withIds = false): array
    {
        $results = [];
        /** @var AbstractModel $item */
        foreach ($this->items as $item) {
            if ($withIds) {
                $results[$item->getData($this->getIndexField())] = $item->getDataUsingMethod($field);
                continue;
            }

            $results[] = $item->getDataUsingMethod($field);
        }

        return $results;
    }

    /**
     * Get the items with the specified keys.
     *
     * @param mixed $keys
     *
     * @return array
     */
    public function only($keys)
    {
        $keys = is_array($keys) ? $keys : func_get_args();

        $only = [];
        foreach ($this->items as $item) {
            if (is_array($item)) {
                $only[] = array_intersect($item, array_flip($keys));
            } elseif (method_exists($item, 'toArray')) {
                $only[] = array_intersect_key($item->toArray(), array_flip($keys));
            }
        }

        return $only;
    }

    /**
     * @param int $itemsCount
     * @param int $pageSize
     *
     * @return $this
     */
    public function setTotalPagesCount(int $itemsCount, int $pageSize): static
    {
        if ($pageSize <= 0) {
            throw new \InvalidArgumentException('Argument $pageSize is not valid');
        }

        $this->pagesCount = (int)ceil($itemsCount / $pageSize);

        return $this;
    }

    /**
     * @return int
     */
    public function getTotalPagesCount(): int
    {
        return $this->pagesCount;
    }

    /**
     * @param int $itemsCount
     */
    public function setTotalItemsCount(int $itemsCount): void
    {
        $this->itemsCount = $itemsCount;
    }

    /**
     * @return int
     */
    public function getTotalItemsCount(): int
    {
        return $this->itemsCount;
    }

    /**
     * @param array $searchCriteria
     *
     * @return $this
     */
    public function setSearchCriteria(array $searchCriteria): static
    {
        $this->clear();

        if (isset($searchCriteria[SearchCriteria::FILTER])) {
            foreach ($searchCriteria[SearchCriteria::FILTER] as $group) {
                $this->addFilterGroups($group);
            }
        }

        if (isset($searchCriteria[SearchCriteria::SORT])) {
            foreach ($searchCriteria[SearchCriteria::SORT] as $sort) {
                $this->setOrder($sort['field'], $sort['order'] ?? $sort['direction'] ?? SearchCriteria::SORT_ORDER_ASC);
            }
        }

        if (isset($searchCriteria[SearchCriteria::PAGINATION])) {
            $this->setLimit(
                $searchCriteria[SearchCriteria::PAGINATION]['limit'] ?? SearchCriteria::DEFAULT_PAGE_SIZE,
                $searchCriteria[SearchCriteria::PAGINATION]['page'] ?? SearchCriteria::DEFAULT_PAGE
            );
        }

        return $this;
    }

    /**
     * @param array $group
     *
     * @return $this
     */
    protected function addFilterGroups(array $group): static
    {
        $this->filterGroups[] = $group;

        return $this;
    }

    /**
     * @param string $field
     * @param string $direction
     *
     * @return $this
     */
    public function setOrder(string $field, string $direction = SearchCriteria::SORT_ORDER_ASC): static
    {
        $this->orders[$field] = $direction;

        return $this;
    }

    /**
     * @return array
     */
    protected function getOrders(): array
    {
        return $this->orders;
    }

    /**
     * @param int $count
     * @param int $page
     *
     * @return $this
     */
    protected function setLimit(int $count, int $page = SearchCriteria::DEFAULT_PAGE): static
    {
        $this->limit = $count;
        $this->page = $page;

        return $this;
    }

    /**
     * @return int|null
     */
    public function getLimit(): int|null
    {
        return $this->limit !== -1 ? $this->limit : null;
    }

    /**
     * @return int
     */
    public function getPage(): int
    {
        return $this->page;
    }

    /**
     * @param string|array               $field
     * @param array|null                 $filter
     * @param string                     $multiModeLogic which OR or AND logic to use for multi-fields or
     *                                                   multi-condition mode
     *
     * @return $this
     * @todo implement new method for field as array
     * NOTE: Don't pass nested and root filter into this method in the same time
     */
    public function addFieldToFilter(
        array|string $field,
        array|null $filter = null,
        string $multiModeLogic = SearchCriteria::CONDITION_OR
    ): static {
        // multiple fields OR/AND case: addFieldToFilter(
        //      [
        //         'f1' => 'text',
        //         'f2' => ['ne' => 111]
        //      ],
        //      condition for this group AND/OR
        // )
        if (is_array($field)) {
            $fields = [];
            foreach ($field as $fieldName => $conditionField) {
                $fields[] = $this->getFieldCondition($fieldName, $conditionField);
            }

            $this->filterGroups[] = $this->getFilterGroup($fields, $multiModeLogic);

            return $this;
        }

        // single field case
        if (is_string($field)) {
            $fields = $this->getFieldCondition($field, $filter);
            $groupCondition = SearchCriteria::CONDITION_AND;
            // addFieldToFilter($field, [['operator1' => $value1], ['operator2' => $value2]]) case - OR/AND logic
            if (is_numeric(key($fields))) {
                $groupCondition = $multiModeLogic;
            } else {
                $fields = [$fields];
            }

            $group = $this->getFilterGroup($fields, $groupCondition);

            // Merge AND condition fields all into one group;
            // needed when calling subsequently addFieldToFilter() for different fields
            if ($groupCondition == SearchCriteria::CONDITION_AND && $this->canMergeFilterGroups()) {
                if (empty($this->filterGroups[SearchCriteria::CONDITION_AND])) {
                    $this->filterGroups[SearchCriteria::CONDITION_AND] = $group;
                } else {
                    $this->filterGroups[SearchCriteria::CONDITION_AND]['group'] = array_merge(
                        $this->filterGroups[SearchCriteria::CONDITION_AND]['group'],
                        $fields
                    );
                }
            } else {
                $this->filterGroups[] = $group;
            }
        }

        return $this;
    }

    /**
     * @return bool
     */
    protected function canMergeFilterGroups()
    {
        return $this->filterLogic == SearchCriteria::CONDITION_AND;
    }

    /**
     * @param string                      $field
     * @param string|array|int|null|float $condition
     *
     * @return array
     */
    protected function getFieldCondition(string $field, array|string|float|int|bool|null $condition): array
    {
        // addFieldToFilter($field, $value) case
        if (!is_array($condition)) {
            return [
                'field'    => $field,
                'operator' => SearchCriteria::EQ,
                'value'    => $condition,
            ];
        }

        // addFieldToFilter($field, ['from' => $from, 'to' => $to]) case
        if (key_exists('from', $condition) && key_exists('to', $condition)) {
            return [
                'field'    => $field,
                'operator' => SearchCriteria::BETWEEN,
                'value'    => [
                    'from' => $condition['from'],
                    'to'   => $condition['to'],
                ],
            ];
        }

        // addFieldToFilter($field, ['operator' => $value]) case
        $operator = key($condition);
        if (is_string($operator)) {
            if (in_array($operator, SearchCriteria::SUPPORTED_OPERATORS)) {
                if ($operator == SearchCriteria::BETWEEN) {
                    return [
                        'field'    => $field,
                        'operator' => $operator,
                        'value'    => [
                            'from' => $condition[$operator]['from'],
                            'to'   => $condition[$operator]['to'],
                        ],
                    ];
                }

                if ($operator == SearchCriteria::EXISTS || $operator == SearchCriteria::NOT_EXISTS) {
                    return [
                        'field'    => $field,
                        'operator' => $operator,
                    ];
                }

                $value = $condition[$operator];
                if (!is_array($value) && ($operator == SearchCriteria::IN || $operator == SearchCriteria::NOT_IN)) {
                    $value = [$value];
                }

                return [
                    'field'    => $field,
                    'operator' => $operator,
                    'value'    => $value,
                ];
            } else {
                // By default enforce addFieldToFilter($field, $value) case
                return [
                    'field'    => $field,
                    'operator' => SearchCriteria::EQ,
                    'value'    => str_replace("\n", '', print_r($condition, true)),
                ];
            }
        }

        // addFieldToFilter($field, [['operator1' => $value1], ['operator2' => $value2]]) case - OR/AND logic
        $fields = [];
        foreach ($condition as $conditionValue) {
            $fields[] = $this->getFieldCondition($field, $conditionValue);
        }

        return $fields;
    }

    /**
     * @param array  $fields
     * @param string $condition
     *
     * @return array
     */
    protected function getFilterGroup(array $fields, string $condition = SearchCriteria::CONDITION_AND): array
    {
        return [
            'condition' => $condition,
            'group'     => $fields
        ];
    }

    /**
     * @param string $filterLogic
     *
     * @return $this
     */
    public function setFilterLogic(string $filterLogic): self
    {
        $this->filterLogic = $filterLogic;

        return $this;
    }

    /**
     * @return string
     */
    public function getFilterLogic(): string
    {
        return $this->filterLogic ?? SearchCriteria::CONDITION_AND;
    }

    /**
     * @return array
     */
    protected function getFilterGroups(): array
    {
        return $this->filterGroups;
    }

    /**
     * @param string[]|string $field
     *
     * @return $this
     */
    public function addFieldToSelect(array|string $field): static
    {
        if (empty($field)) {

            return $this;
        }

        if (is_array($field)) {
            foreach ($field as $value) {
                $this->addFieldToSelect($value);
            }

            return $this;
        }

        $this->fieldsToSelect[] = $field;

        return $this;
    }

    /**
     * Add IDs filter
     *
     * @param array $ids
     *
     * @return $this
     */
    public function addIdsFilter(array $ids): static
    {
        if (empty($ids)) {

            return $this;
        }
        if (!$this->getFlag('ids_filter_applied')) {
            // Note: array_values() needed so that array elements have sequential keys.
            // Input $ids array may contain values like this [1 => 'value', 0 => 'value2', 3 => 'value3'].
            // If keys are not sequential, ES will treat the field not as array but object
            $ids = array_values(array_unique($ids));
            $this->addFieldToFilter($this->getIndexField(), [SearchCriteria::IN => $ids]);
            $this->setFlag('ids_filter_applied', true);
        }

        return $this;
    }

    /**
     * @return array
     */
    protected function getFieldsToSelect(): array
    {
        return $this->fieldsToSelect;
    }

    abstract public function load(string $slug = null): static;
    abstract public function getData(): array;
}
