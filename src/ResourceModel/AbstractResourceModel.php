<?php
declare(strict_types=1);

namespace Levtechdev\Simpaas\ResourceModel;

use Levtechdev\Simpaas\Model\AbstractModel;
use Levtechdev\Simpaas\Database\DbAdapterInterface;

abstract class AbstractResourceModel
{
    const TABLE_NAME      = 'default';
    const CONNECTION_NAME = 'default';

    const DATE_FIELDS = ['created_at', 'updated_at', 'expired_at', 'executed_at'];

    const MAX_RESULT = 10000;

    /**
     * @var string
     */
    protected string $connectionName = 'default';

    protected $connection;
    /**
     * @var DbAdapterInterface
     */
    protected DbAdapterInterface $adapter;

    public function __construct(DbAdapterInterface $dbAdapter)
    {
        $this->adapter = $dbAdapter;
    }

    /**
     * @param AbstractModel $object
     * @param string|int|float|bool|null $id
     * @param string $field
     * @param array $excludedFields
     *
     * @return $this
     */
    final public function load(
        AbstractModel $object,
        string|int|float|bool|null $id,
        string $field = AbstractModel::ID_FIELD_NAME,
        array $excludedFields = []
    ): static {
        $this->beforeLoad($object);

        $this->objectLoad($object, $id,  $field, $excludedFields);

        $this->afterLoad($object);

        return $this;
    }

    /**
     * @param AbstractModel $object
     *
     * @return $this
     */
    final public function save(AbstractModel $object): static
    {
        $this->beforeSave($object);

        $this->objectSave($object);

        $this->afterSave($object);

        return $this;
    }

    /**
     * @param AbstractModel $object
     *
     * @return $this
     */
    final public function delete(AbstractModel $object): static
    {
        $this->beforeDelete($object);

        $this->objectDelete($object);

        $this->afterDelete($object);

        return $this;
    }

    /**
     * @param AbstractModel $object
     *
     * @return $this
     */
    public function beforeLoad(AbstractModel $object): static
    {
        return $this;
    }

    /**
     * @param AbstractModel $object
     *
     * @return $this
     */
    public function afterLoad(AbstractModel $object): static
    {
        return $this;
    }

    /**
     * @param AbstractModel $object
     *
     * @return $this
     */
    public function beforeSave(AbstractModel $object): static
    {
        return $this;
    }

    /**
     * @param AbstractModel $object
     *
     * @return $this
     */
    public function afterSave(AbstractModel $object): static
    {
        return $this;
    }

    /**
     * @param AbstractModel $object
     *
     * @return $this
     */
    public function beforeDelete(AbstractModel $object): static
    {
        return $this;
    }

    /**
     * Perform actions after entity delete
     *
     * @param AbstractModel $object
     *
     * @return $this
     */
    public function afterDelete(AbstractModel $object): static
    {
        return $this;
    }

    public function getConnection()
    {
        return $this->connection;
    }

    /**
     * @param AbstractModel $object
     * @param array|string|int $ids
     *
     * @return int
     */
    abstract public function exists(AbstractModel $object, array|string|int $ids): int;

    /**
     * @param AbstractModel $object
     * @param array|string|int|float|bool|null $ids
     * @param string $fieldName
     * @return int
     */
    abstract public function existsByField(AbstractModel $object, array|string|int|float|bool|null $ids, string $fieldName): int;

    /**
     * @param AbstractModel $object
     * @param string|int|float|bool|null $id
     * @param string $field
     * @param array $excludedFields
     *
     * @return void
     */
    abstract protected function objectLoad(
        AbstractModel $object,
        string|int|float|bool|null $id,
        string $field = AbstractModel::ID_FIELD_NAME,
        array $excludedFields = []
    ): void;

    /**
     * @return string
     */
    public function getConnectionName() : string
    {
        return $this->connectionName;
    }

    /**
     * @param string $connectionName
     *
     * @return $this
     */
    public function setConnectionName(string $connectionName): self
    {
        $this->connectionName = $connectionName;

        return $this;
    }

    /**
     * @param AbstractModel $object
     *
     * @return void
     */
    abstract protected function objectSave(AbstractModel $object): void;

    /**
     * @param AbstractModel $object
     *
     * @return void
     */
    abstract protected function objectDelete(AbstractModel $object): void;

    /**
     * @return mixed
     */
    abstract public function getAdapter(): DbAdapterInterface;
}
