<?php

namespace Levtechdev\Simpaas\Middleware\Http;

use Illuminate\Http\Request;
use Swaggest\JsonSchema\Schema;
use Levtechdev\Simpaas\Exceptions\BadRequestException;
use Levtechdev\Simpaas\Exceptions\SchemaNotDefinedException;
use Levtechdev\Simpaas\Exceptions\SchemaNotValidException;
use Levtechdev\Simpaas\Helper\SystemInfo;

class JsonSchemaMiddleware
{
    public const NAME = 'json-schema';

    const JSON_SCHEMA_PATH_MASK = '%s' . DS . 'public' . DS . 'json-schema' . DS . '%s' . DS . '%s';

    const JSON_SCHEMA_SUFFIX_SINGLE            = 'single';
    const JSON_SCHEMA_SUFFIX_BULK              = 'bulk';
    const JSON_SCHEMA_SUFFIX_SEARCH            = 'search';
    const JSON_SCHEMA_SUFFIX_UPDATE_BY_FILTER  = 'filter';

    // @todo reimlpement it that way so no hardcoded specific API URL suffixes are defined here
    const JSON_SCHEMA_ALLOWED_POST_SUFFIXES = [
        self::JSON_SCHEMA_SUFFIX_BULK,
        self::JSON_SCHEMA_SUFFIX_SEARCH,
        'autocomplete_search',
        'navigation_by_phrase',
        'navigation_by_url_key',
        'export',
    ];

    const JSON_SCHEMA_ALLOWED_PUT_SUFFIXES = [
        self::JSON_SCHEMA_SUFFIX_BULK,
        self::JSON_SCHEMA_SUFFIX_UPDATE_BY_FILTER,
        'qty',
        'visibility',
    ];

    const JSON_SCHEMA_ALLOWED_DELETE_SUFFIXES = [
        self::JSON_SCHEMA_SUFFIX_BULK,
    ];

    public function __construct(protected Schema $schema, protected SystemInfo $systemHelper)
    {
    }

    /**
     * @param Request  $request
     * @param \Closure $next
     *
     * @return mixed
     *
     * @throws BadRequestException
     * @throws SchemaNotValidException
     */
    public function handle(Request $request, \Closure $next): mixed
    {
        try {
            $path = $this->systemHelper->getCleanPath();
            $pathParts = explode('/', $path);

            // get substring before _ it will be folder
            $filepath = $this->getFilePath($pathParts[0]);

            // if received not like v%d, then set current api version
            if (strlen($pathParts[0]) > 2) {
                array_unshift($pathParts, config('global.api_version'));
            }

            $method = $request->getMethod();

            // for url like POST /v1/resources or PUT /v1/resources/id
            $suffix = self::JSON_SCHEMA_SUFFIX_SINGLE;
            $lastPathPart = end($pathParts);

            // for url like POST /v1/resources/bulk or POST /v1/resources/search
            if ($method == \Symfony\Component\HttpFoundation\Request::METHOD_POST
                && in_array($lastPathPart, self::JSON_SCHEMA_ALLOWED_POST_SUFFIXES)
            ) {
                $suffix = $lastPathPart;
            }

            // for url like PUT /v1/resources/bulk or PUT /v1/resources/filter
            if ($method == \Symfony\Component\HttpFoundation\Request::METHOD_PUT
                && in_array($lastPathPart, self::JSON_SCHEMA_ALLOWED_PUT_SUFFIXES)
            ) {
                $suffix = $lastPathPart;
            }

            // for url like DELETE /v1/resources/bulk
            if ($method == \Symfony\Component\HttpFoundation\Request::METHOD_DELETE
                && in_array($lastPathPart, self::JSON_SCHEMA_ALLOWED_DELETE_SUFFIXES)
            ) {
                $suffix = $lastPathPart;
            }

            list($apiVersion, $fileName) = $pathParts;

            if (!empty($pathParts[3])) {
                $additionalPartFileName = $pathParts[3];
                $fileName .= '_' . $additionalPartFileName;
            }

            $fullFileName = $this->getFullFileName(
                $filepath,
                $fileName,
                $request->getMethod(),
                $suffix
            );

            $jsonRequest = $request->getContent();

            // Ignore not existing JSON Schema for DELETE HTTP/API calls, otherwise enforce it
            if ($method == \Symfony\Component\HttpFoundation\Request::METHOD_DELETE) {
                $fullFilePath = sprintf(self::JSON_SCHEMA_PATH_MASK, base_path(), $apiVersion, $fullFileName);
                if (!file_exists($fullFilePath)) {

                    return $next($request);
                }
            }

            $schema = Schema::import($this->getSchema($apiVersion, $fullFileName, $filepath));

            $schema->in(json_decode($jsonRequest, false, 512, JSON_THROW_ON_ERROR));
        } catch (\Throwable $e) {
            if ($e instanceof \Swaggest\JsonSchema\Exception || $e instanceof \JsonException) {
                throw new SchemaNotValidException($e->getMessage());
            }
            throw new BadRequestException($e->getMessage());
        }

        return $next($request);
    }

    /**
     * @param string $value
     *
     * @return string
     */
    protected function getFilePath(string $value): string
    {
        $tempArr = explode('_', $value);

        return $tempArr[0];
    }

    /**
     * @param string $apiVersion
     * @param string $fullFileName
     * @param string $filepath
     *
     * @return mixed
     *
     * @throws SchemaNotDefinedException
     * @throws \JsonException
     */
    protected function getSchema(string $apiVersion, string $fullFileName, string $filepath): mixed
    {
        $fullFilePath = sprintf(self::JSON_SCHEMA_PATH_MASK, base_path(), $apiVersion, $fullFileName);

        if (!file_exists($fullFilePath)) {
            throw new SchemaNotDefinedException();
        }

        /**
         * Added in order to support relative references of json schemas
         *
         * Replaces "./" with the base path on top hierarchy files
         */
        $schema = file_get_contents($fullFilePath);
        $schema = str_replace(
        '"./',
        '"' . sprintf(self::JSON_SCHEMA_PATH_MASK, base_path(), $apiVersion, $filepath) . '/',
        $schema
        );

        return json_decode($schema, false, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * @param string $filepath
     * @param string $fileName
     * @param string $httpMethod
     * @param string $action
     *
     * @return string
     */
    protected function getFullFileName(string $filepath, string $fileName, string $httpMethod, string $action = 'single'): string
    {
        $action = preg_replace('/[^-_a-zA-Z]/i', '', $action);
        $fileName = $filepath . '/' . $fileName . '_' . strtolower($httpMethod) . '_' . strtolower($action);

        return $fileName . '.json';
    }
}
