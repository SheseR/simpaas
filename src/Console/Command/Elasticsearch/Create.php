<?php

namespace Levtechdev\Simpaas\Console\Command\Elasticsearch;

use Exception;
use Levtechdev\Simpaas\Console\Command\AbstractCommand;
use Levtechdev\Simpaas\Model\Elasticsearch\EntityResourceModelMapperInterface;
use Levtechdev\Simpaas\ResourceModel\Elasticsearch\AbstractElasticsearchResourceModel;

class Create extends AbstractCommand
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'elastic:create';

    protected $signature = 'elastic:create {entityType}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create entity index in ElasticSearch';

    /**
     * @param EntityResourceModelMapperInterface $helper
     *
     * @return void
     *
     * @throws Exception
     */
    public function handle(EntityResourceModelMapperInterface $helper)
    {
        $entityType = $this->argument('entityType');
        if (empty($entityType)) {
            throw new Exception('Entity type is not specified');
        }

        $this->createIndex($entityType, $helper);
    }

    /**
     * @param string $entityType
     * @param EntityResourceModelMapperInterface $helper
     */
    protected function createIndex(string $entityType, EntityResourceModelMapperInterface $helper)
    {
        try {
            $resourceModelClass = $helper->getResourceModelClassNameByEntityType($entityType);

            if (!$resourceModelClass || !class_exists($resourceModelClass)) {
                throw new Exception('Entity "' . $entityType . '" is not defined (resource model does not exists)');
            }
            /** @var AbstractElasticsearchResourceModel $resourceModel */
            $resourceModel = app()->make($resourceModelClass);
            $time = microtime(true);

            $indexName = $resourceModel->createIndex();
            if ($indexName !== false) {
                $this->info(sprintf('Created index "%s" for entity type "%s", took %s s', $indexName, $entityType, microtime(true) - $time));
            } else {
                $this->error('Index could not be successfully created or DB request timed out');
            }
        } catch (\Throwable  $e) {
            $message = sprintf('Cannot create index - %s', $e->getMessage());
            $this->error($message, $e->getTraceAsString());
        }
    }
}