<?php
namespace Levtechdev\SimPaas\Model\Mysql;

use Levtechdev\SimPaas\Authorization\Helper\Auth;
use Levtechdev\SimPaas\Helper\RandomHash;
use Levtechdev\SimPaas\Model\AbstractModel;
use Levtechdev\SimPaas\ResourceModel\Mysql\AbstractMysqlResourceModel;

/**
 * @method array getLockedAttributes()
 */
abstract class AbstractMysqlModel extends AbstractModel
{
    public function __construct(
        AbstractMysqlResourceModel $model,
        RandomHash $hashHelper,
        protected Auth $authHelper,
        array $data = [])
    {
        parent::__construct($model, $hashHelper, $data);
    }

    /**
     * @param array $data
     * @return $this
     */
    protected function createNewObject(array $data = []): static
    {
        return new static($this->getResource(), $this->hashHelper, $this->getAuthHelper(), $data);
    }

    /**
     * @return array
     */
    public function getMappedData(): array
    {
        $fieldTypes = $this->getResource()->getJsonFieldTypes();
        $obscuredFields = $this->getResource()->getJsonObscuredFields();
        $hiddenFields = $this->getResource()::SYSTEM_FIELDS;

        foreach ($fieldTypes as $field => $type) {
            if ($type == 'boolean' && $this->hasData($field)) {
                $this->setData($field, (bool)$this->getData($field));
            }
        }

        foreach ($obscuredFields as $field) {
            $this->setData($field, '****');
        }

        foreach ($hiddenFields as $field) {
            $this->unsetData($field);
        }

        return $this->getData();
    }

    /**
     * @return Auth
     */
    public function getAuthHelper(): Auth
    {
        return $this->authHelper;
    }

    /**
     * Revert changes to a locked attributes if anyone not PIM attempts to change them
     *
     * @return $this
     */
    public function prepareLockedAttributes(): static
    {
        if ($this->isObjectNew() || !$this->hasDataChanges()) {

            return $this;
        }

        $lockedAttributes = array_keys($this->getLockedAttributes() ?? []);
        if (empty($lockedAttributes)) {

            return $this;
        }

        // allow PIM always to reset any locked attributes, so "return" needed here
        if ($this->canOverrideLockedAttributes()) {

            return $this;
        }

        /**
         * Revert changes to a locked attributes
         */
        foreach ($lockedAttributes as $key) {
            $newData = $this->getData($key);
            $origData = $this->getOrigData($key);
            if ($newData != $origData) {
                $this->setData($key, $origData);
            }
        }

        return $this;
    }

    /**
     * @return bool
     */
    public function canOverrideLockedAttributes(): bool
    {
        return $this->getAuthHelper()->isClientPim();
    }
}