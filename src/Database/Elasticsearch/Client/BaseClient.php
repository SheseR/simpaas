<?php
declare(strict_types=1);

namespace Levtechdev\Simpaas\Database\Elasticsearch\Client;

use Elasticsearch\Client;
use Elasticsearch\Common\Exceptions\NoNodesAvailableException;

class BaseClient extends Client
{
    const MAX_QUERY_SIZE = 15000000; // 15000000 = 14.3051 mb ES optimal bulk size 10-15mb (limit 40mb)

    /**
     * $params['index']                  = (string) Default index for items which don't provide one
     * $params['type']                   = DEPRECATED (string) Default document type for items which don't provide one
     * $params['wait_for_active_shards'] = (string) Sets the number of shard copies that must be active before proceeding with the bulk operation. Defaults to 1, meaning the primary shard only. Set to `all` for all shard copies, otherwise set to any non-negative value less than or equal to the total number of copies for the shard (number of replicas + 1)
     * $params['refresh']                = (enum) If `true` then refresh the affected shards to make this operation visible to search, if `wait_for` then wait for a refresh to make this operation visible to search, if `false` (the default) then do nothing with refreshes. (Options = true,false,wait_for)
     * $params['routing']                = (string) Specific routing value
     * $params['timeout']                = (time) Explicit operation timeout
     * $params['_source']                = (list) True or false to return the _source field or not, or default list of fields to return, can be overridden on each sub-request
     * $params['_source_excludes']       = (list) Default list of fields to exclude from the returned _source field, can be overridden on each sub-request
     * $params['_source_includes']       = (list) Default list of fields to extract and return from the _source field, can be overridden on each sub-request
     * $params['pipeline']               = (string) The pipeline id to preprocess incoming documents with
     * $params['body']                   = (array) The operation definition and data (action-data pairs), separated by newlines (Required)
     *
     * @param array $params Associative array of parameters
     *
     * @return array
     * @throws NoNodesAvailableException
     * @see    https://www.elastic.co/guide/en/elasticsearch/reference/master/docs-bulk.html
     */
    public function bulk(array $params = []): array
    {
        /**
         * Issue AAI-1246: Fixed 413 error from Elasticsearch - large request on stage ETL
         * @customization START
         */
        $results = [];
        $querySize = 0;

        // rebuilding body request into multiple requests when needed
        $body = $params['body'];
        $params['body'] = [];

        $preparedParams = $params;

        /**
         * POST _bulk -> supported multiple operations. Possible values: index, create, update, delete (it w/o fields).
         *
         * POST _bulk
         * { "index" : { "_index" : "test", "_id" : "1" } }
         * { "field1" : "value1" }
         * { "delete" : { "_index" : "test", "_id" : "2" } }
         * { "create" : { "_index" : "test", "_id" : "3" } }
         * { "field1" : "value3" }
         * { "update" : {"_id" : "1", "_index" : "test"} }
         * { "doc" : {"field2" : "value2"} }
         */
        foreach ($body as $value) {
            $indexAction = $value['index'] ?? $value['create'] ?? $value['update'] ?? $value['delete'] ?? false;
            if (!empty($indexAction['_index'])) {
                $preparedParams['body'][] = $value;

                continue;
            }

            // calculate only data from documents without _index and _id params
            $itemSize = strlen(json_encode($value));
            $querySize += $itemSize;

            if (count($preparedParams['body']) > 1 && $querySize >= self::MAX_QUERY_SIZE) {
                $lastIndexAction = array_pop($preparedParams['body']);
                $results[] = parent::bulk($preparedParams);

                // reset executed data and set last index information as first element array
                $preparedParams['body'] = [$lastIndexAction];
                $querySize = $itemSize;
            }

            $preparedParams['body'][] = $value;
        }

        $results[] = parent::bulk($preparedParams);

        return $this->getResultRequest($results);

        /** @customization END */
    }

    /**
     * @param array $results
     *
     * @return array
     */
    protected function getResultRequest(array $results): array
    {
        if (count($results) === 1) {

            return array_shift($results);
        }

        $took = 0; // How long, in milliseconds, it took to process the bulk request
        $error = false;
        $items = [];

        foreach ($results as $result) {
            if ($result['errors'] === true) {
                $error = true;
            }

            $took += $result['took'] ?? 0;
            $items = array_merge($items, $result['items']);
        }

        return [
            'error' => $error,
            'took'  => $took,
            'items' => $items,
        ];
    }
}
