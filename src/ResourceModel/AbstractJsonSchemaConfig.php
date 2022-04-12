<?php

namespace Levtechdev\Simpaas\ResourceModel;

use Levtechdev\Simpaas\Model\DataObject;

abstract class AbstractJsonSchemaConfig
{
    const JSON_SCHEMA_PATH_MASK = '%s' . DS . 'public' . DS . 'json-schema' . DS . '%s' . DS . 'definitions' . DS . '%s';

    public function __construct(protected DataObject $dataObject)
    {

    }

    /**
     * Get current api version
     *
     * @return string
     */
    public function getApiVersion(): string
    {
        return config('global.api_version');
    }
}