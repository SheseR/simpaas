<?php

namespace Levtechdev\Simpaas\ResourceModel\Mysql;

use JsonException;
use Levtechdev\Simpaas\Exceptions\EntityNotDefinedException;
use Levtechdev\Simpaas\ResourceModel\AbstractJsonSchemaConfig;

class JsonSchemaConfig extends AbstractJsonSchemaConfig
{
    /**
     * Entity field types
     */
    protected array $fieldTypes = [];

    /**
     * List of fields which value must be obscured to API responses
     */
    protected array $obscuredFields = [];

    /**
     * @param string $entityType
     *
     * @return array
     *
     * @throws EntityNotDefinedException
     * @throws JsonException
     */
    public function getFieldTypes(string $entityType): array
    {
        $this->make($entityType);

        return $this->fieldTypes[$entityType];
    }

    /**
     * @param string $entityType
     * @return array
     *
     * @throws EntityNotDefinedException
     * @throws JsonException
     */
    public function getObscuredFields(string $entityType): array
    {
        $this->make($entityType);

        return $this->obscuredFields[$entityType] ?? [];
    }

    /**
     * @param string $entityType
     *
     * @return void
     *
     * @throws EntityNotDefinedException
     * @throws JsonException
     */
    protected function make(string $entityType)
    {
        if (!array_key_exists($entityType, $this->fieldTypes)) {
            $this->processConfig($entityType);
        }
    }

    /**
     * @param string $entityType
     *
     * @return void
     *
     * @throws EntityNotDefinedException
     * @throws JsonException
     */
    protected function processConfig(string $entityType)
    {
        $apiVersion = $this->getApiVersion();
        $fullFilePath = sprintf(static::JSON_SCHEMA_PATH_MASK, base_path(), $apiVersion, $entityType . '.json');
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
     * @param array $jsonSchema
     * @param int|string $entityType
     * @param string $configType
     *
     * @return void
     */
    protected function processJsonProperties(array $jsonSchema, int|string $entityType, string $configType): void
    {
        if (empty($jsonSchema[$entityType][$configType])) {

            return;
        }

        $this->processJsonPropertiesBySchema($jsonSchema[$entityType][$configType], $entityType);
    }

    /**
     * @param array $schema
     * @param string $entityType
     *
     * @return void
     */
    protected function processJsonPropertiesBySchema(array $schema, string $entityType): void
    {
        foreach ($schema as $field => $params) {
            $this->fieldTypes[$entityType][$field] = $params['type'];

            if (!empty($params['obscured'])) {
                $this->obscuredFields[$entityType][] = $field;
            }
        }
    }
}
