<?php

namespace Levtechdev\SimPaas\Helper;

use Levtechdev\SimPaas\Exceptions\EntityNotDefinedException;
use App\Core\Model\DataObject;

class JsonSchemaConfig extends Core
{
    const JSON_SCHEMA_PATH_MASK = '%s' . DS . 'public' . DS . 'json-schema' . DS . '%s' . DS . 'definitions' . DS . '%s';

    /**
     * Entity field types
     */
    protected array $fieldTypes = [];

    /**
     * List of fields which value must be obscured to API responses
     */
    protected array $obscuredFields = [];

    /**
     * @var DataObject
     */
    protected DataObject $dataObject;

    public function __construct(DataObject $dataObject)
    {
        $this->dataObject = $dataObject;
    }

    /**
     * @param string $entityType
     *
     * @return mixed
     * @throws EntityNotDefinedException
     */
    public function getFieldTypes($entityType)
    {
        $this->make($entityType);

        return $this->fieldTypes[$entityType];
    }

    /**
     * @param string $entityType
     *
     * @return DataObject|mixed
     * @throws EntityNotDefinedException
     */
    public function getObscuredFields($entityType)
    {
        $this->make($entityType);

        return $this->obscuredFields[$entityType] ?? [];
    }

    /**
     * @param string $entityType
     *
     * @throws EntityNotDefinedException
     */
    protected function make($entityType)
    {
        if (!array_key_exists($entityType, $this->fieldTypes)) {
            $this->processConfig($entityType);
        }
    }

    /**
     * @param $entityType
     *
     * @throws SimPass\Exceptions\EntityNotDefinedException
     */
    protected function processConfig($entityType)
    {
        $apiVersion = $this->getApiVersion();
        $fullFilePath = sprintf(self::JSON_SCHEMA_PATH_MASK, base_path(), $apiVersion, $entityType . '.json');
        if (!file_exists($fullFilePath)) {
            throw new EntityNotDefinedException();
        }

        $schema = file_get_contents($fullFilePath);
        $schema = str_replace('"./', '"' . sprintf(self::JSON_SCHEMA_PATH_MASK, base_path(), $apiVersion, ''), $schema);

        $jsonSchema = json_decode($schema, true, 512, JSON_THROW_ON_ERROR);

        if (empty($jsonSchema[$entityType]['properties'])) {
            throw new EntityNotDefinedException();
        }

        $this->processJsonProperties($jsonSchema, $entityType, 'properties');

        if (empty($this->fieldTypes[$entityType])) {
            throw new EntityNotDefinedException();
        }
    }

    /**
     * @param array      $jsonSchema
     * @param int|string $entityType
     * @param string     $configType
     *
     * @return $this
     */
    protected function processJsonProperties($jsonSchema, $entityType, $configType)
    {
        if (empty($jsonSchema[$entityType][$configType])) {

            return $this;
        }

        $this->processJsonPropertiesBySchema($jsonSchema[$entityType][$configType], $entityType, $configType);

        return $this;
    }

    /**
     * @param $schema
     * @param $entityType
     * @param $configType
     *
     * @return $this
     */
    protected function processJsonPropertiesBySchema($schema, $entityType, $configType)
    {
        foreach ($schema as $field => $params) {
            $this->fieldTypes[$entityType][$field] = $params['type'];

            if (!empty($params['obscured'])) {
                $this->obscuredFields[$entityType][] = $field;
            }
        }

        return $this;
    }
}
