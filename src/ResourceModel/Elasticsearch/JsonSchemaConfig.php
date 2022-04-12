<?php
namespace Levtechdev\Simpaas\ResourceModel\Elasticsearch;

use Levtechdev\Simpaas\Exceptions\EntityNotDefinedException;
use Levtechdev\Simpaas\Model\DataObject;
use Levtechdev\Simpaas\ResourceModel\AbstractJsonSchemaConfig;
use mysql_xdevapi\DatabaseObject;

class JsonSchemaConfig extends AbstractJsonSchemaConfig
{
    /**
     * ElasticSearch document fields mapping by entity type
     *
     * @var array
     */
    protected array $mapping = [];

    /**
     * ElasticSearch document default field values by entity type
     *
     * @var array
     */
    protected array $defaultValues = [];

    /**
     * ElasticSearch document fields analyzers by entity type
     *
     * @var array
     */
    protected array $analyzers = [];

    /**
     * ElasticSearch document unique fields by entity type
     *
     * @var array
     */
    protected array $uniqueFields = [];

    /**
     * ElasticSearch document dynamic fields list by entity type
     *
     * @var array
     */
    protected array $dynamicTemplates = [];

    /**
     * @var array
     */
    protected array $systemFields = [];


    public function getMapping($entityType)
    {
        $this->make($entityType);

        return $this->mapping[$entityType];
    }

    /**
     * @param string $entityType
     *
     * @return DataObject
     */
    public function getDefaultValues(string $entityType): DataObject
    {
        $this->make($entityType);

        return $this->defaultValues[$entityType] ?? $this->dataObject->factoryCreate();
    }

    /**
     * @param string $entityType
     *
     * @return DataObject
     */
    public function getAnalyzers(string $entityType): DataObject
    {
        $this->make($entityType);

        return $this->analyzers[$entityType] ?? $this->dataObject->factoryCreate();
    }

    /**
     * @param string $entityType
     *
     * @return array
     */
    public function getUniqueFields(string $entityType): array
    {
        $this->make($entityType);

        return $this->uniqueFields[$entityType] ?? [];
    }

    /**
     * @param string $entityType
     *
     * @return array
     */
    public function getSystemFields(string $entityType): array
    {
        $this->make($entityType);

        return $this->systemFields[$entityType] ?? [];
    }

    /**
     * @param string $entityType
     *
     * @return array
     */
    public function getDynamicTemplates(string $entityType): array
    {
        $this->make($entityType);

        return $this->dynamicTemplates[$entityType] ?? [];
    }

    /**
     * @param string $entityType
     *
     * @return void
     *
     * @throws EntityNotDefinedException
     * @throws \JsonException
     */
    protected function make(string $entityType): void
    {
        if (!array_key_exists($entityType, $this->mapping)) {
            $this->processConfig($entityType);
        }
    }

    /**
     * @param string $entityType
     *
     * @return void
     *
     * @throws EntityNotDefinedException
     * @throws \JsonException
     */
    protected function processConfig(string $entityType): void
    {
        $apiVersion = $this->getApiVersion();
        $fullFilePath = sprintf(self::JSON_SCHEMA_PATH_MASK, base_path(), $apiVersion, $entityType . '.json');
        if (!file_exists($fullFilePath)) {
            throw new EntityNotDefinedException();
        }

        $schema = file_get_contents($fullFilePath);
        $schema = str_replace('"./', '"' . sprintf(self::JSON_SCHEMA_PATH_MASK, base_path(), $apiVersion, ''), $schema);

        $jsonSchema = json_decode($schema, true, 512, JSON_THROW_ON_ERROR);

        if (empty($jsonSchema[$entityType]['properties']) && empty($jsonSchema[$entityType]['additionalMappedProperties'])) {
            throw new EntityNotDefinedException();
        }

        $this->processJsonProperties($jsonSchema, $entityType, 'properties');

        if (!empty($jsonSchema[$entityType]['additionalMappedProperties'])) {
            $this->processJsonProperties($jsonSchema, $entityType, 'additionalMappedProperties');
        }

        if (empty($this->mapping[$entityType])) {
            throw new EntityNotDefinedException();
        }
    }

    /**
     * @param array $jsonSchema
     * @param string $entityType
     * @param string $configType
     *
     * @return void
     */
    protected function processJsonProperties(array $jsonSchema, string $entityType, string $configType): void
    {
        if (empty($jsonSchema[$entityType][$configType])) {

            return;
        }

        $this->processJsonPropertiesBySchema($jsonSchema[$entityType][$configType], $entityType, $configType);
    }

    /**
     * @param array $schema
     * @param string $entityType
     * @param string $configType
     * @param string|null $parentField
     *
     * @return void
     */
    protected function processJsonPropertiesBySchema(array $schema, string $entityType, string $configType, ?string $parentField = null): void
    {
        foreach ($schema as $field => $params) {
            if (empty($params['config'])) {
                continue;
            }

            // properties for which not needed handling children elements
            if ($parentField === null) {
                if (!empty($params['config']['mapping'])) {
                    $this->mapping[$entityType][$field] = $params['config']['mapping'];

                    if ($configType == 'additionalMappedProperties') {
                        if (!key_exists($entityType, $this->systemFields)) {
                            $this->systemFields[$entityType] = [];
                        }
                        $this->systemFields[$entityType][] = $field;
                    }
                }
                if (key_exists('default_value', $params['config'])) {
                    if (!array_key_exists($entityType, $this->defaultValues)) {
                        $this->defaultValues[$entityType] = $this->dataObject->factoryCreate();
                    }
                    $this->defaultValues[$entityType]->setData($field, $params['config']['default_value']);
                }
                if (!empty($params['config']['unique_field'])) {
                    $this->uniqueFields[$entityType][] = is_bool($params['config']['unique_field'])
                        ? $field
                        : $params['config']['unique_field'];
                }
                if (!empty($params['config']['dynamic_templates'])) {
                    $this->dynamicTemplates[$entityType] = array_merge(
                        $this->dynamicTemplates[$entityType] ?? [],
                        $params['config']['dynamic_templates']
                    );
                }
            }

            $pathField = !empty($parentField) ? $parentField . '.' . $field : $field; // dynamic name for parent path
            if (key_exists('default_value', $params['config'])) {
                if (!array_key_exists($entityType, $this->defaultValues)) {
                    $this->defaultValues[$entityType] = $this->dataObject->factoryCreate();
                }
                $this->defaultValues[$entityType]->setData($pathField, $params['config']['default_value']);
            }
            if (!empty($params['config']['mapping']['analyzer'])) {
                if (!array_key_exists($entityType, $this->analyzers)) {
                    $this->analyzers[$entityType] = $this->dataObject->factoryCreate();
                }
                $this->analyzers[$entityType]->setData($pathField, $params['config']['mapping']['analyzer']);
            }

            // recursive add properties from children
            if (!empty($params['config']['mapping']['properties'])) {
                $data = [];
                foreach ($params['config']['mapping']['properties'] as $propertyField => $property) {
                    $data[$propertyField]['config']['mapping'] = $property;
                }

                if (!empty($data)) {
                    $parentFieldKeep = !empty($parentField) ? $parentField . '.' . $field : $field;
                    $this->processJsonPropertiesBySchema($data, $entityType, $configType, $parentFieldKeep);
                }
            }
        }
    }
}