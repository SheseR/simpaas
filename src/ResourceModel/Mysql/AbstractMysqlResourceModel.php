<?php
namespace Levtechdev\SimPaas\ResourceModel\Mysql;

use Illuminate\Database\Query\Builder;
use Levtechdev\SimPaas\Database\DbAdapterInterface;
use Levtechdev\SimPaas\Exceptions\MysqlCallbackException;
use Levtechdev\SimPaas\Database\Mysql\MysqlAdapter;
use Levtechdev\SimPaas\Exceptions\CouldNotDeleteEntity;
use Levtechdev\SimPaas\Exceptions\EntityFieldNotUniqueException;
use Levtechdev\SimPaas\Exceptions\EntityNotFoundException;
use Levtechdev\SimPaas\Helper\JsonSchemaConfig;
use Levtechdev\SimPaas\Model\AbstractModel;
use Levtechdev\SimPaas\Model\DataObject;
use Levtechdev\SimPaas\Model\Mysql\AbstractMysqlModel;
use Levtechdev\SimPaas\ResourceModel\AbstractResourceModel;
use Levtechdev\SimPaas\Exceptions\EntityNotDefinedException;
use Levtechdev\SimPaas\Database\Mysql\Expression\MergeJsonObjectExpression;
use Levtechdev\SimPaas\Database\Mysql\Expression\LockedAttributesExpression;

abstract class AbstractMysqlResourceModel extends AbstractResourceModel
{
    const TABLE_NAME = 'default';

    const JSON_FIELD_TYPE = 'json';
    const INT_FIELD_TYPE = 'int';

    const SYSTEM_FIELDS = [];
    const ALLOWED_LOCKED_ATTRIBUTES = [];
    const LOCKED_ATTRIBUTES_FIELD = 'locked_attributes';

    protected JsonSchemaConfig $jsonSchemaConfig;
    protected array $jsonFieldTypes = [];
    protected array $jsonObscuredFields = [];
    protected array $mainTableColumnsCache = [];

    /**
     * Flag to determine if JSON Schema definition must be used to enforce certain entity data mapping
     */
    protected bool $useJsonSchemaConfig = false;

    public function __construct(MysqlAdapter $adapter, JsonSchemaConfig $jsonSchemaConfig)
    {
        parent::__construct($adapter);

        $this->connection = $adapter->getConnection();

        $this->jsonSchemaConfig = $jsonSchemaConfig;
        if ($this->useJsonSchemaConfig) {
            $this->configureEntity();
        }
    }

    /**
     * @return DbAdapterInterface|MysqlAdapter
     */
    public function getAdapter(): DbAdapterInterface|MysqlAdapter
    {
        return $this->adapter;
    }

    /**
     * @return Builder
     */
    public function getNewQueryBuilder(): Builder
    {
        return $this->getAdapter()->getConnection()->query();
    }

    /**
     * Prepare entity config/settings (fields mapping, default values, unique fields etc.) from json-schema definitions
     *
     * @return void
     *
     * @throws EntityNotDefinedException
     */
    protected function configureEntity(): void
    {
        $entityType = static::TABLE_NAME;
        $this->jsonFieldTypes = $this->jsonSchemaConfig->getFieldTypes($entityType);
        $this->jsonObscuredFields = $this->jsonSchemaConfig->getObscuredFields($entityType);
    }

    /**
     * @return bool
     */
    public function isEntityDataLockable(): bool
    {
        return array_key_exists(self::LOCKED_ATTRIBUTES_FIELD, $this->getMainTableColumns());
    }

    /**
     * @return array
     */
    public function getJsonFieldTypes(): array
    {
        return $this->jsonFieldTypes;
    }

    /**
     * @return array
     */
    public function getJsonObscuredFields(): array
    {
        return $this->jsonObscuredFields;
    }

    /**
     * @param AbstractModel|AbstractMysqlModel $object
     * @return $this
     */
    public function beforeSave(AbstractModel|AbstractMysqlModel $object): static
    {
        parent::beforeSave($object);

        $object->unsetData(['updated_at', 'created_at']);

        return $this;
    }

    /**
     * @return string
     */
    public function getMainTable(): string
    {
        return static::TABLE_NAME;
    }

    /**
     * @param bool $includeSystem
     *
     * @return array
     */
    public function getEntityColumns(bool $includeSystem = false): array
    {
        $describedTableColumns = $this->getMainTableColumns();

        if (!$includeSystem) {
            unset(
                $describedTableColumns['created_at'],
                $describedTableColumns['updated_at'],
                $describedTableColumns[AbstractModel::ID_FIELD_NAME]
            );
        }

        return $describedTableColumns;
    }

    /**
     * @return array
     */
    public function getRequiredAttributeCodes(): array
    {
        $describedTableColumns = $this->getMainTableColumns();
        $systemFields = ['created_at', 'update_at', AbstractModel::ID_FIELD_NAME];
        $requiredFields = [];
        foreach ($describedTableColumns as $fieldName => $value) {
            if (in_array($fieldName, $systemFields)) {

                continue;
            }

            if ($value['Default'] === null && $value['Null'] === 'NO') {
                $requiredFields[] = $fieldName;
            }
        }

        return $requiredFields;
    }

    /**
     * @return array
     */
    public function getMainTableColumns(): array
    {
        if (!empty($this->mainTableColumnsCache)) {

            return $this->mainTableColumnsCache;
        }

        $ddlDescribe = $this->getAdapter()->describeTable($this->getMainTable());
        foreach ($ddlDescribe as $column) {
            $this->mainTableColumnsCache[$column['Field']] = $column;
        }

        return $this->mainTableColumnsCache;
    }

    /**
     * Encode JSON attributes, lock/update locked attributes
     *
     * @param DataObject $object
     * @param bool|null $canOverrideLockedAttributes
     *         true - (PIM scenario) the affected attributes will be locked and updated
     *         else - affected locked attributes will will not be updated
     *         null - locked attributes will be forcefully updated
     * @param array $fieldsList specific list of fields to be extracted for resulting data
     *
     * @return array
     */
    public function prepareDataForSave(
        DataObject $object,
        ?bool      $canOverrideLockedAttributes = false,
        array      $fieldsList = []
    ): array
    {
        $preparedData = $lockedAttributes = [];
        $columns = $this->getEntityColumns();
        if (!empty($fieldsList)) {
            $columns = array_intersect_key($columns, array_flip($fieldsList));
        }
        $isEntityDataLockable = $this->isEntityDataLockable();

        // By default, when data is inserted (new object), need to define an empty "{}" locked_attributes field value,
        // there can be new objects and non-new objects in the batch, so locked_attributes we need to add always
        if ($canOverrideLockedAttributes !== null && $isEntityDataLockable) {
            $preparedData[self::LOCKED_ATTRIBUTES_FIELD] = new MergeJsonObjectExpression(
                self::LOCKED_ATTRIBUTES_FIELD, []
            );
        }

        foreach ($object->getData() as $key => $item) {
            if (!array_key_exists($key, $columns)) {
                continue;
            }

            if ($columns[$key]['Type'] == self::JSON_FIELD_TYPE && $item !== null) {
                $item = json_encode($item);
            }

            if ($canOverrideLockedAttributes === null) {
                $preparedData[$key] = $item;

                continue;
            }

            if ($isEntityDataLockable && in_array($key, static::ALLOWED_LOCKED_ATTRIBUTES)) {
                if ($canOverrideLockedAttributes) {
                    $lockedAttributes[$key] = true;
                } else {
                    $item = new LockedAttributesExpression($key, $item);
                }
            }

            $preparedData[$key] = $item;
        }

        if ($canOverrideLockedAttributes && !empty($lockedAttributes)) {
            $preparedData[self::LOCKED_ATTRIBUTES_FIELD] = new MergeJsonObjectExpression(
                self::LOCKED_ATTRIBUTES_FIELD,
                $lockedAttributes
            );
        }

        return $preparedData;
    }

    /**
     * @param DataObject $object
     * @param bool|null $canOverrideLockedAttributes
     * @param array $fieldsList
     *
     * @return array
     */
    public function prepareDataForUpsert(
        DataObject $object,
        ?bool      $canOverrideLockedAttributes = false,
        array      $fieldsList = []
    ): array
    {
        $dataForSave = $this->prepareDataForSave($object, $canOverrideLockedAttributes, $fieldsList);

        $columns = $this->getEntityColumns();
        foreach ($columns as $columnName => $describe) {
            if (!array_key_exists($columnName, $dataForSave)) {
                // required field validation is run above
                $dataForSave[$columnName] = $describe['Default'];
            }
        }

        return $dataForSave;
    }

    /**
     * @param array $record
     *
     * @return array
     */
    public function processRecord(array $record): array
    {
        $fields = $this->getMainTableColumns();
        $record = is_numeric(key($record)) ? $record[key($record)] : $record;
        $result = [];
        foreach ($record as $field => $value) {
            if (!empty($value) && !empty($fields[$field]) && $fields[$field]['Type'] == self::JSON_FIELD_TYPE) {
                $value = json_decode($value, true, 10);
            }

            // The case is SELECT parent_images->\'$[*].original_url\' as json_src_parent_images
            if (!empty($value) && empty($fields[$field]) && substr($field, 0, 4) == self::JSON_FIELD_TYPE) {
                $value = json_decode($value, true, 10);
            }

            if (!empty($fields[$field]) && $fields[$field]['Type'] == self::INT_FIELD_TYPE) {
                $value = (int)$value;
            }

            $result[$field] = $value;
        }

        return $result;
    }

    /**
     * Check if specified IDs exist in DB
     *
     * @param AbstractModel|AbstractMysqlModel $object
     * @param array|string|int $ids
     *
     * @return int
     */
    public function exists(AbstractModel|AbstractMysqlModel $object, array|string|int $ids): int
    {
        return $this->existsByField($object, $ids, AbstractModel::ID_FIELD_NAME);
    }

    /**
     * Check if specified IDs exist in DB
     *
     * @param AbstractModel|AbstractMysqlModel $object
     * @param array|string|int|float|bool|null $ids
     * @param string $fieldName
     *
     * @return int
     */
    public function existsByField(AbstractModel|AbstractMysqlModel $object, array|string|int|float|bool|null $ids, string $fieldName): int
    {
        $ids = array_unique((array)$ids);
        if (count($ids) == 0) {

            return 0;
        }

        return $this->getAdapter()->recordsExist($this->getMainTable(), $ids, $fieldName);
    }

    /**
     * @param AbstractModel|AbstractMysqlModel $object
     *
     * @return $this
     *
     * @throws EntityFieldNotUniqueException
     * @throws MysqlCallbackException
     * @throws \Throwable
     */
    protected function objectSave(AbstractModel|AbstractMysqlModel $object): void
    {
        try {
            if ($object->isObjectNew()) {
                $id = $this->getAdapter()->insertArray(
                    $this->getMainTable(),
                    $this->prepareDataForSave($object, $object->canOverrideLockedAttributes())
                );

                // mysql return 0 for last inserted id when PRIMARY key are not autoincrement. Try keep already generated ID
                if ($id !== 0) {
                    $object->setId($id);
                }
            } else {
                $this->getAdapter()->updateRecord(
                    $this->getMainTable(),
                    $this->prepareDataForSave($object, $object->canOverrideLockedAttributes()),
                    $object->getId(),
                    $object->getIdFieldName()
                );
            }
        } catch (\Illuminate\Database\QueryException $e) {
            $originMessage = $e->getMessage();
            // Handle integrity violation SQLSTATE 23000 for duplicate keys
            if ($e->getCode() == 23000) {
                $uniqueKey = strtok(
                    substr($originMessage, strpos($originMessage, 'for key') + strlen("for key")),
                    " "
                );

                throw new EntityFieldNotUniqueException($object->getId(), [], [$uniqueKey], 400);
            }
        } catch (\Throwable $e) {

            // @todo
            throw $e;
        }
    }

    /**
     * @param AbstractModel $object
     * @param float|bool|int|string|null $id
     * @param string $field
     * @param array $excludedFields
     * @return void
     * @throws EntityNotFoundException
     */
    protected function objectLoad(
        AbstractModel              $object,
        float|bool|int|string|null $id,
        string                     $field = AbstractModel::ID_FIELD_NAME,
        array                      $excludedFields = []
    ): void
    {
        if ($field === null) {
            $field = $object::ID_FIELD_NAME;
        }

        $record = $this->getAdapter()->selectRecord($this->getMainTable(), $id, $field);
        if (empty($record)) {
            throw new EntityNotFoundException(
                sprintf('Entity "%s" was not found by "%s" = "%s" condition', get_class($object), $field, $id)
            );
        }

        $data = $this->processRecord($record);
        $object->setData($data);
        $object->setId($data[$object::ID_FIELD_NAME]);
        $object->isObjectNew(false);
    }

    /**
     * @param AbstractModel $object
     *
     * @return void
     *
     * @throws CouldNotDeleteEntity
     * @throws MysqlCallbackException
     */
    protected function objectDelete(AbstractModel $object): void
    {
        if (!$object->getId()) {
            throw new CouldNotDeleteEntity();
        }

        $affected = $this->getAdapter()->deleteRecord($this->getMainTable(), $object->getId(), $object::ID_FIELD_NAME);
        if ($affected < 1) {
            throw new CouldNotDeleteEntity();
        }
    }
}