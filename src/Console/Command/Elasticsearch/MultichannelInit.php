<?php

namespace Levtechdev\Simpaas\Console\Command\Elasticsearch;

use Levtechdev\Simpaas\Console\Command\AbstractCommand;
use Levtechdev\Simpaas\Helper\Logger;
use Levtechdev\Simpaas\Model\Elasticsearch\EntityResourceModelMapperInterface;
use Levtechdev\Simpaas\ResourceModel\Elasticsearch\AbstractElasticsearchResourceModel;

class MultichannelInit extends AbstractCommand
{
    const CHANNEL  = 'multichannel_init';
    const LOG_FILE = 'multichannel_init_error.log';

    const MULTICHANNEL_INDEXES = [
        'frontend_catalog_product_v2',
        'catalog_navigation',
        'catalog_product',
        'catalog_product_ranking',
        'catalog_activity_log',
    ];

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'elastic:multichannel';

    /** @var string */
    protected $signature = 'elastic:multichannel {--entityType=} {--deleteOldIndex}';

    /** @var string */
    protected $description = 'Update aliases and index names as Multichannel in ElasticSearch';

    /**
     * @param EntityResourceModelMapperInterface $helper
     * @param Logger $loggerHelper
     *
     * @return void
     *
     * @throws \Exception
     */
    public function handle(EntityResourceModelMapperInterface $helper, Logger $loggerHelper)
    {
        $this->logger = $loggerHelper->getLogger(self::CHANNEL, base_path(Logger::LOGS_DIR . self::LOG_FILE));

        $entityType = $this->option('entityType');
        if (empty($entityType)) {
            foreach (self::MULTICHANNEL_INDEXES as $entityType) {
                $this->migrateIndex($entityType, $helper);
            }
        } else {
            $this->migrateIndex($entityType, $helper);
        }
    }

    /**
     * @param string $entityType
     * @param EntityResourceModelMapperInterface $helper
     *
     * @return void
     */
    protected function migrateIndex(string $entityType, EntityResourceModelMapperInterface $helper)
    {
        try {
            $time = microtime(true);

            $resourceModelClass = $helper->getResourceModelClassNameByEntityType($entityType);
            if (!$resourceModelClass || !class_exists($resourceModelClass)) {
                throw new \Exception('Entity "' . $entityType . '" is not defined (resource model does not exists)');
            }

            /** @var AbstractElasticsearchResourceModel $resourceModel */
            $resourceModel = app()->make($resourceModelClass);
            $newIndexName = $resourceModel->migrateIndexToMultiChannelIndex($this->option('deleteOldIndex'));

            $this->info(sprintf(
                'Reindexed "%s" entity ES index, new index is "%s", took %s s',
                $entityType, $newIndexName, microtime(true) - $time)
            );
        } catch (\Throwable $e) {
            $message = sprintf('ES Reindex Error: %s', $e->getMessage());
            $this->error($message);
            $this->logger->error($e);
        }
    }
}
