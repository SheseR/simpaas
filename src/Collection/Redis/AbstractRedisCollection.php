<?php

namespace Levtechdev\SimPaas\Collection\Redis;

use Levtechdev\SimPaas\Collection\AbstractCollection;
use Levtechdev\SimPaas\Database\SearchCriteria;
use Levtechdev\SimPaas\Exceptions\NotImplementedException;
use Levtechdev\SimPaas\Model\AbstractModel;
use Levtechdev\SimPaas\Model\Redis\AbstractRedisModel;

abstract class AbstractRedisCollection extends AbstractCollection
{
    protected array  $searchCriteria = [];

    const FILTER_IDS_KEY = 'ids';

    /** @var array */
    protected array $ids = [];

    /** @var array */
    protected array $idKeys = [];

    public function __construct(AbstractRedisModel $model, array $items = [], string $indexField = AbstractModel::ID_FIELD_NAME)
    {
        parent::__construct($model, $items, $indexField);
    }

    /**
     * @return $this
     */
    public function clear(): self
    {
        parent::clear();

        $this->searchCriteria = [];

        return $this;
    }

    /**
     * @return array
     * @todo this implementation is not efficient because the entire data collection will be loaded just to retrieve IDs
     */
    public function getIds(): array
    {
        return $this->ids;
    }

    /**
     * @return array
     * @todo this implementation is not efficient because the entire data collection will be loaded just to retrieve IDs
     */
    public function getIdKeys(): array
    {
        return $this->idKeys;
    }

    /**
     * @param string|null $slug
     * @return $this
     */
    public function load(string $slug = null): static
    {
        if ($this->isLoaded()) {

            return $this;
        }

        $resourceModel = $this->getModel()->getResource();

        $idsToFilter = $this->getIdsFilterData();
        if (empty($idsToFilter)) {
            $idKeys = $resourceModel->getConnection()->keys($this->getModel()::ENTITY . "*");
        } else {
            $idKeys = array_map(
                function ($item) use ($resourceModel) {
                    return $resourceModel->getIdKey($this->getModel()::ENTITY, $item);
                },
                $idsToFilter
            );
        }

        if (!empty($this->searchCriteria[SearchCriteria::PAGINATION])) {
            $idKeys = array_slice($idKeys, 0, $this->searchCriteria[SearchCriteria::PAGINATION]['limit']);
            unset($this->searchCriteria[SearchCriteria::PAGINATION]);
        }

        $items = null;
        if (!empty($idKeys)) {
            $items = $resourceModel->getConnection()->mget($idKeys);
        }

        if (!$items) {

            return $this;
        }

        $items = $this->processItems($items);

        if (!empty($this->getPreparedQuery())) {
            $items = $this->filterItems($items);
        }

        foreach ($items as $itemData) {
            $dataModel = $this->getModel()->factoryCreate($itemData);
            $dataModel->setHasDataChanges(false);
            $this->addItem($dataModel);
            $this->ids[] = $itemData['id'];
            $this->idKeys[] = $resourceModel->getIdKey($this->getModel()::ENTITY, $itemData['id']);
        }

        $this->setIsLoaded();

        return $this;
    }

    /**
     * @throws NotImplementedException
     */
    public function getData(): array
    {
        throw new NotImplementedException(__METHOD__);
    }

    /**
     * @param array $rawItems
     *
     * @return array
     */
    protected function processItems(array $rawItems): array
    {
        return array_map(
            function ($item) {
                return json_decode($item, true);
            },
            $rawItems
        );
    }

    /**
     * @return array
     */
    protected function getIdsFilterData(): array
    {
        return (array)($this->getPreparedQuery()[self::FILTER_IDS_KEY] ?? []);
    }

    /**
     * Supports only AND logic for now
     *
     * $filters = [
     *     'ids' => [1, 2, 3, 4],
     *     'group' => ['eq' => 'jobg_SHDJKLs89sdjkl'],
     * ]
     *
     * @param array $items
     *
     * @return array
     */
    protected function filterItems(array $items): array
    {
        $dateFields = $this->getModel()->getResource()::DATE_FIELDS;

        foreach ($items as $key => $data) {
            foreach ($this->getPreparedQuery() as $field => $filter) {
                if ($field == self::FILTER_IDS_KEY) {
                    continue;
                }

                $operand = key($filter);

                $value = current($filter);

                if (!array_key_exists($field, $data)) {
                    unset($items[$key]);
                    break;
                }

                $sourceValue = $data[$field];
                $filterValue = $value;
                if (in_array($field, $dateFields)) {
                    $filterValue = strtotime($filterValue);
                    $sourceValue = strtotime($sourceValue);
                }

                // AND logic
                if (!$this->checkValue($operand, $filterValue, $sourceValue)) {
                    unset($items[$key]);
                    break;
                }
            }
        }

        return $items;
    }

    /**
     * @param string $operand
     * @param mixed  $filterValue
     * @param mixed  $sourceValue
     *
     * @return bool
     */
    protected function checkValue(string $operand, mixed $filterValue, mixed $sourceValue): bool
    {
        return match($operand) {
            SearchCriteria::EQ => $filterValue === $sourceValue,
            SearchCriteria::NEQ => $filterValue !== $sourceValue,
            SearchCriteria::GTE => $filterValue >= $sourceValue,
            SearchCriteria::LTE => $filterValue <= $sourceValue,
            default => throw new \InvalidArgumentException(
                sprintf('Collection filter operand "%s" is not supported at %s', $operand, get_class($this))
            ),
        };
    }

    /**
     * @param array $searchCriteria
     *
     * @return $this
     */
    public function setSearchCriteria(array $searchCriteria): static
    {
        $this->clear();
        $this->searchCriteria = $searchCriteria;

        return $this;
    }

    /**
     * @return array
     */
    protected function getPreparedQuery(): array
    {
        return $this->searchCriteria;
    }
}