<?php

namespace Levtechdev\Simpaas\Model\Elasticsearch;

use Elasticsearch\Common\Exceptions\NoNodesAvailableException;
use Levtechdev\Simpaas\ElasticSearch\Processor\BulkProcessor;
use Levtechdev\Simpaas\Helper\DateHelper;
use Levtechdev\Simpaas\Helper\Language;
use Levtechdev\Simpaas\Helper\RandomHash;
use Levtechdev\Simpaas\Model\AbstractModel;
use Levtechdev\Simpaas\ResourceModel\Elasticsearch\AbstractElasticsearchResourceModel;
use Throwable;

class AbstractElasticsearchModel extends AbstractModel
{
    const ENTITY           = 'catalog';
    const ENTITY_ID_PREFIX = 'ctlg_';

    const DATA_FIELD_ANALYZERS = [
        'english'  => 'mb_strtolower',
        'standard' => 'mb_strtolower',
    ];

    const EXCLUDE_FIELDS = [];
    const INCLUDE_FIELDS = [];

    public function __construct(
        AbstractElasticsearchResourceModel $resourceModel,
        RandomHash $hashHelper,
        protected Language $languageHelper,
        protected BulkProcessor $bulkProcessor,
        array $data = []
    ) {
        parent::__construct($resourceModel, $hashHelper, $data);
    }

    /**
     * @param array $data
     *
     * @return $this
     */
    protected function createNewObject(array $data = []): static
    {
        return new static($this->getResource(), $this->hashHelper, $this->languageHelper, $this->bulkProcessor, $data);
    }

    /**
     * @param array|int|string $ids
     *
     * @return int
     */
    public function exists(int|string|array $ids): int
    {
        return $this->getResource()->exists($this, $ids);
    }

    /**
     * Set system/default data before save
     *
     * @return $this
     */
    public function prepareData(): static
    {
        parent::prepareData();

        $date = date(DateHelper::DATE_TIME_FORMAT);
        if ($this->isObjectNew()) {
            $defaultDataValues = $this->getResource()->getDefaultValues()->getData();
            $this->addData($this->arrayMergeRecursive($defaultDataValues, $this->getData()));

            $this->setData('created_at', $date);
            $this->setData('updated_at', $date);
        } else {
            if ($this->hasDataChanges()) {
                $this->setData('updated_at',$date);
            }
        }

        $this->keepTypeEmptyFields();

        return $this;
    }

    /**
     * @return $this
     */
    protected function keepTypeEmptyFields(): self
    {
        if (!$this->hasDataChanges()) {

            return $this;
        }

        foreach ($this->getData() as $fieldName => $item) {
            if (!empty($item) || $item === null) {
                continue;
            }

            $fieldType = $this->getResource()->getMapping()[$fieldName]['type'] ?? '';
            if ($fieldType === 'object') {
                $this->setDataUsingMethod($fieldName, new \stdClass());
            }
        }

        return $this;
    }

    /**
     * Get object data changes but do not collect system attributes changes
     *
     * @param bool $includeSystemAttributes
     *
     * @return array
     */
    public function getDataChanges(bool $includeSystemAttributes = false): array
    {
        $results = [];
        $changes = parent::getDataChanges();

        $mapping = $this->getResource()->getAllowedFields();
        $systemAttributes = array_flip($this->getResource()->getSystemFields());
        foreach ($changes as $key => $value) {
            if (key_exists($key, $systemAttributes)) {
                if ($includeSystemAttributes && $this->hasChangedSystemAttributes($value)) {
                    $results[$key] = $value;
                }

                continue;
            }

            if (!key_exists($key, $mapping)) {

                continue;
            }

            $results[$key] = $value;
        }

        return $results;
    }

    /**
     * Simple field structure - array('was' => 'status', 'became' => null)
     * Object field structure - array(0 => ['was' => 'status', 'became' => null, ...])
     *
     * @param array $value
     *
     * @return bool
     */
    protected function hasChangedSystemAttributes(array $value): bool
    {
        $value = array_key_exists('became', $value) ? [$value] : $value;
        foreach ($value as $item) {
            if ($item['became'] != $item['was']) {

                return true;
            }
        }

        return false;
    }

    /**
     * @todo for now only a first level of data fields is considered. Inner structure of the fields checking not implemented yet
     *
     * @return array
     */
    public function getMappedData(): array
    {
        $selectLanguage = $this->languageHelper->getLanguage();
        $convertedData = [];
        $mapping = $this->getResource()->getAllowedFields();

        foreach(static::INCLUDE_FIELDS as $field => $fieldProperty) {
            $mapping[$field] = $fieldProperty;
        }

        foreach ($this->getData() as $key => $value) {
            if ($key !== 'id' && !key_exists($key, $mapping)) {

                continue;
            }
            if (in_array($key, static::EXCLUDE_FIELDS)) {

                continue;
            }

            $convertedData[$key] = $value;
        }

        return $this->convertDataByMap($convertedData, $selectLanguage);
    }

    /**
     * @return Language
     */
    public function getLanguageHelper(): Language
    {
        return $this->languageHelper;
    }

    /**
     * @param mixed $data
     * @param null|string $selectLanguage
     *
     * @return array
     */
    protected function convertDataByMap(mixed $data, ?string $selectLanguage = null): array
    {
        $convertedData = [];
        foreach ($data as $key => $value) {
            if ($selectLanguage != null && is_array($value)) {
                if (!empty($value[$selectLanguage])) {
                    $value = $value[$selectLanguage];
                    $convertedData[$key] = $value;
                } elseif (key_exists(Language::DEFAULT_LANG, $value)) {
                    $value = $value[Language::DEFAULT_LANG];
                    $convertedData[$key] = $value;
                } else {
                    $convertedData[$key] = $this->convertDataByMap($value, $selectLanguage);
                }
            } else {
                $convertedData[$key] = $value;
            }
        }

        return $convertedData;
    }

    /**
     * @param bool|null $waitForData
     * @return $this
     *
     * @throws NoNodesAvailableException
     * @throws Throwable
     */
    public function afterSaveCommit(bool $waitForData = null): static
    {
        if ($waitForData === null) {
            $waitForData = $this->getResource()->getAdapter()->getCurrentEnforcedWriteMode();
        }
        $this->getBulkProcessor()->commit($waitForData);

        return $this;
    }

    /**
     * @return BulkProcessor
     */
    public function getBulkProcessor(): BulkProcessor
    {
        return $this->bulkProcessor;
    }

    /**
     * Used to determine if forced entity save (refresh=wait_for) must be invoked on ES level
     *
     * @return bool
     */
    public function hasSignificantChanges(): bool
    {
        return false;
    }
}