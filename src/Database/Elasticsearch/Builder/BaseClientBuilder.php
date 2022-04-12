<?php
declare(strict_types=1);

namespace Levtechdev\Simpaas\Database\Elasticsearch\Builder;

use Elasticsearch\Client;
use Elasticsearch\ClientBuilder;
use Elasticsearch\Transport;
use Levtechdev\Simpaas\Database\ElasticSearch\Client\BaseClient;

class BaseClientBuilder extends ClientBuilder
{
    protected function instantiate(Transport $transport, callable $endpoint, array $registeredNamespaces): Client
    {
        /**
         * @customization START
         */
        return new BaseClient($transport, $endpoint, $registeredNamespaces);
        /** @customization END */
    }
}
