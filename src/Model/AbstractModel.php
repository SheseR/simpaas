<?php

namespace Levtechdev\Simpaas\Model;

use Levtechdev\Simpaas\ResourceModel\AbstractResourceModel;
use Levtechdev\Simpaas\Exceptions\EntityNotFoundException;
use Levtechdev\Simpaas\Helper\RandomHash;
use Exception;

/**
 * @method bool getIsMassSaveEvent()
 * @method AbstractModel setIsMassSaveEvent(bool $flag)
 * @method string getComment()
 */
abstract class AbstractModel extends DataObject
{
    const ENTITY           = 'abstract_model';
    const ENTITY_ID_PREFIX = 'mdl_';
    const ENTITY_ID_LENGTH = 14;
    const ID_FIELD_NAME    = 'id';
    const SLUG_FIELD_NAME  = 'slug';

    const FILTERABLE_JSON_FIELDS = [];

    /**
     * Name of object id field
     *
     * @var string|null
     */
    protected ?string $idFieldName = null;

    /**
     * Name of object id field
     *
     * @var string|null
     */
    protected ?string $slugFieldName = null;

    /**
     * Original data that was loaded
     *
     * @var array
     */
    protected array $origData = [];

    /** @var null|bool */
    protected ?bool $isObjectNew = null;

    /** @var mixed  */
    protected mixed $resource;

    /**
     * @var RandomHash
     */
    protected RandomHash $hashHelper;


    public function __construct(AbstractResourceModel $resourceModel, RandomHash $hashHelper, array $data = [])
    {
        $this->resource = $resourceModel;
        $this->hashHelper = $hashHelper;

        parent::__construct($data);
    }

    /**
     * @param array $data
     *
     * @return $this|AbstractModel
     */
    public function factoryCreate($data = []): static
    {
        $obj = $this->createNewObject($data);
        // copy data to original data
        $obj->setHasDataChanges(false);

        return $obj;
    }

    /**
     * @return string
     */
    public function generateUniqueId(): string
    {
        return $this->hashHelper->generate(static::ENTITY_ID_LENGTH, static::ENTITY_ID_PREFIX);
    }

    /**
     * Identifier getter
     *
     * @return string|int|null
     */
    public function getId(): string|int|null
    {
        return $this->getData($this->getIdFieldName());
    }

    /**
     * @param string|int $value
     * @return $this
     */
    public function setId(string|int $value): static
    {
        $this->setData($this->getIdFieldName(), $value);

        return $this;
    }

    /**
     * Id field name setter
     *
     * @param string $name
     *
     * @return $this
     */
    public function setIdFieldName(string $name): static
    {
        $this->idFieldName = $name;

        return $this;
    }

    /**
     * Id field name getter
     *
     * @return string
     */
    public function getIdFieldName(): string
    {
        return $this->idFieldName ?? static::ID_FIELD_NAME;
    }

    /**
     * Slug field name setter
     *
     * @param string $fieldName
     *
     * @return $this
     */
    public function setSlugFieldName(string $fieldName): static
    {
        $this->slugFieldName = $fieldName;

        return $this;
    }

    /**
     * Slug field name getter
     *
     * @return string
     */
    public function getSlugFieldName(): string
    {
        return $this->slugFieldName ?? static::SLUG_FIELD_NAME;
    }

    /**
     * @param bool $flag
     *
     * @return $this
     */
    public function setHasDataChanges(bool $flag): static
    {
        parent::setHasDataChanges($flag);

        // Copy $this->data to $this->origData when resetting changes flag
        if ($flag === false) {
            $this->setOrigData();
        }

        return $this;
    }

    /**
     * Object original data getter
     *
     * If $key is not defined will return all the original data as an array
     * Otherwise it will return value of the element specified by $key
     *
     * It is possible to use keys like a.b.c for access nested array data
     *
     * @param string $key
     *
     * @return mixed
     */
    public function getOrigData(string $key = ''): mixed
    {
        if ($key === '') {

            return $this->origData;
        }

        if (empty($this->origData) || !is_array($this->origData)) {

            return $this->origData;
        }

        /* process a.b.c key as ['a']['b']['c'] */
        if (strpos($key, self::DATA_KEY_PATH_DELIMITER)) {
            $keys = $subKeys = explode(self::DATA_KEY_PATH_DELIMITER, $key);
            $data = $this->origData;

            foreach ($keys as $key) {
                if ((array)$data === $data && key_exists($key, $data)) {
                    $data = $data[$key];
                    array_shift($subKeys);
                } elseif ($data instanceof DataObject) {
                    // maintain relative path for Data Object data elements
                    return $data->getData(implode(self::DATA_KEY_PATH_DELIMITER, $subKeys));
                } else {
                    return null;
                }
            }
        } else {
            $data = null;
            if (key_exists($key, $this->origData)) {

                $data = $this->origData[$key];
            }
        }

        return $data;
    }

    /**
     * Initialize object original data
     *
     * @param string|null $key
     * @param mixed  $data
     *
     * @return $this
     */
    protected function setOrigData(?string $key = null, mixed $data = null): static
    {
        if ($key === null) {
            $this->origData = $this->data;
        } else {
            $this->origData[$key] = $data;
        }

        return $this;
    }

    /**
     * Compare object data with original data
     *
     * It is possible to use keys like a.b.c for access nested array data changes
     *
     * @param array|string $keys
     *
     * @return bool
     */
    public function dataHasChangedFor(array|string $keys): bool
    {
        if ($this->isObjectNew()) {

            return true;
        }

        if (!is_array($keys)) {
            $keys = [$keys];
        }

        $changeDetected = false;
        foreach ($keys as $key) {
            $newData = $this->getData($key);
            $origData = $this->getOrigData($key);
            $changeDetected |= ($newData != $origData);
            if ($changeDetected) {
                break;
            }
        }

        return $changeDetected;
    }

    /**
     * @return array
     */
    public function getDataChanges(): array
    {
        return $this->compareData($this->getOrigData() ?? [], $this->getData());
    }

    /**
     * @param string|int $modelId
     * @param string|null $field
     * @return $this
     */
    public function load(string|int $modelId, ?string $field = null): static
    {
        $this->beforeLoad();
        $this->getResource()->load($this, $modelId, $field);
        $this->afterLoad();
        $this->setHasDataChanges(false);

        return $this;
    }

    /**
     * @return $this
     */
    public function beforeLoad(): static
    {
        event(static::ENTITY . '.model.load.before', ['params' => ['model' => $this]]);

        return $this;
    }

    /**
     * @return $this
     */
    public function afterLoad(): static
    {
        event(static::ENTITY . '.model.load.after', ['params' => ['model' => $this]]);

        if (empty($this->data)) {

            return $this;
        }

        $this->isObjectNew(false);

        return $this;
    }

    /**
     * Save object data
     *
     * @return $this
     * @throws Exception
     *
     */
    public function save(): static
    {
        $this->beforeSave();
        $this->getResource()->save($this);
        $this->afterSave();
        $this->afterSaveCommit();
        $this->setHasDataChanges(false);

        return $this;
    }

    /**
     * @return $this
     */
    public function beforeSave(): static
    {
        event(static::ENTITY . '.model.save.before', ['params' => ['model' => $this, 'comment' => $this->getComment()]]);

        $this->prepareData();

        return $this;
    }

    /**
     * @return $this
     */
    public function prepareData(): static
    {
        return $this;
    }

    /**
     * @return $this
     */
    public function afterSave(): static
    {
        event(
            static::ENTITY . '.model.save.after',
            ['params' => ['model' => $this, 'comment' => $this->getComment()]]
        );

        return $this;
    }

    /**
     * @return $this
     */
    public function afterSaveCommit(): static
    {
        event(static::ENTITY . '.model.save-commit.after', ['params' => ['model' => $this, 'comment' => $this->getComment()]]);

        return $this;
    }

    /**
     * Delete object from database
     *
     * @return $this
     * @throws Exception
     */
    public function delete(): static
    {
        $this->beforeDelete();
        $this->getResource()->delete($this);
        $this->afterDelete();

        return $this;
    }

    /**
     * @return $this
     */
    public function beforeDelete(): static
    {
        event(
            static::ENTITY . '.model.remove.before',
            ['params' => ['model' => $this]]
        );

        return $this;
    }

    /**
     * @return $this
     */
    public function afterDelete(): self
    {
        event(
            static::ENTITY . '.model.remove.after',
            ['params' => ['model' => $this]]
        );

        return $this;
    }

    /**
     * @param int|string|array $ids
     *
     * @return int
     */
    public function exists(int|string|array $ids): int
    {
        return $this->getResource()->exists($this, $ids);
    }

    /**
     * @param int|string|array $ids
     * @param string           $field
     *
     * @return int
     */
    public function existsByField(array|string|int $ids, string $field = self::ID_FIELD_NAME): int
    {
        return $this->getResource()->existsByField($this, $ids, $field);
    }

    /**
     * @param AbstractResourceModel $resource
     *
     * @return $this
     */
    public function setResourceModel(AbstractResourceModel $resource): static
    {
        $this->resource = $resource;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getResource(): mixed
    {
        return $this->resource;
    }

    /**
     * Check object state (true - if object considered newly created - with ID (after persistence) or with ID)
     *
     * This method can help detect if object just created in beforeSave() or afterSave() method
     *
     * @param bool|null $flag
     *
     * @return bool
     */
    public function isObjectNew(bool|null $flag = null): bool
    {
        if ($flag !== null) {
            $this->isObjectNew = $flag;
        }
        if ($this->isObjectNew !== null) {

            return $this->isObjectNew;
        }

        return !(bool)$this->getId();
    }

    /**
     * @param array $data
     *
     * @return $this
     */
    abstract protected function createNewObject(array $data = []): static;
}
