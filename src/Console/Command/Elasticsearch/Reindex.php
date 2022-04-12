<?php
declare(strict_types=1);

namespace Levtechdev\Simpaas\Console\Command\Elasticsearch;

use Levtechdev\Simpaas\Console\Command\AbstractCommand;
use Levtechdev\Simpaas\Helper\Logger;
use Levtechdev\Simpaas\Model\Elasticsearch\EntityResourceModelMapperInterface;
use Levtechdev\Simpaas\ResourceModel\Elasticsearch\AbstractElasticsearchResourceModel;

class Reindex extends AbstractCommand
{
    const CHANNEL  = 'riendex';
    const LOG_FILE = 'reindex_error.log';
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'elastic:index';

    protected $signature = 'elastic:index {entityType} {--createBareIndex} {--deleteOldIndex}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reindex entity index in ElasticSearch';

    /**
     * @param Logger $loggerHelper
     *
     * @throws \Exception
     */
    public function handle(Logger $loggerHelper, EntityResourceModelMapperInterface $entityResourceModelMapper)
    {
        $this->logger = $loggerHelper->getLogger(self::CHANNEL, base_path(Logger::LOGS_DIR . self::LOG_FILE));

        $entityType = $this->argument('entityType');
        $isEmpty = $this->option('createBareIndex');
        if (empty($entityType)) {
            $resourceModels = $entityResourceModelMapper->getInstalledResources();

            foreach ($resourceModels as $entityType => $resourceModel) {
                $this->reindex($entityType, $isEmpty, $entityResourceModelMapper);
            }
        } else {
            $this->reindex($entityType, $isEmpty, $entityResourceModelMapper);
        }
    }

    /**
     * @param string $entityType
     * @param bool $isEmpty
     * @param Core $helper
     */
    protected function reindex(string $entityType, bool $isEmpty, EntityResourceModelMapperInterface $helper)
    {
        try {
            $resourceModelClass = $helper->getResourceModelClassNameByEntityType($entityType);

            if (!$resourceModelClass || !class_exists($resourceModelClass)) {
                throw new \Exception('Entity "' . $entityType . '" is not defined (resource model does not exists)');
            }
            /** @var AbstractElasticsearchResourceModel $resourceModel */
            $resourceModel = app()->make($resourceModelClass);
            $time = microtime(true);

            if ($isEmpty) {
                $newIndexName = $resourceModel->reindexBare($this->option('deleteOldIndex'));
            } else {
                $newIndexName = $resourceModel->reindex($this->option('deleteOldIndex'));
            }

            $this->info(sprintf('Reindexed "%s" entity ES index, new index is "%s", took %s s', $entityType, $newIndexName, microtime(true) - $time));
        } catch (\Throwable $e) {
            $message = sprintf('ES Reindex Error: %s', $e->getMessage());
            $this->error($message);
            $this->logger->error($e);
        }
    }
}