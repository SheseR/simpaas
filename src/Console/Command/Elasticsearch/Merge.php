<?php
declare(strict_types=1);

namespace Levtechdev\Simpaas\Console\Command\Elasticsearch;

use Illuminate\Support\Facades\Artisan;
use Levtechdev\Simpaas\Console\Command\AbstractCommand;
use Levtechdev\Simpaas\Model\Elasticsearch\EntityResourceModelMapperInterface;
use Levtechdev\Simpaas\ResourceModel\Elasticsearch\AbstractElasticsearchResourceModel;

class Merge extends AbstractCommand
{
    const LOG_CHANNEL = 'elastic';
    const LOG_FILE    = 'elastic_merge.log';

    /**
     * How much time to wait after maintenance mode is enabled
     * (maintenance mode propagation takes time on all web nodes)
     */
    const MAINTENANCE_MODE_GRACEFUL_TIMEOUT = 60; // in seconds

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'elastic:merge';

    protected $signature = 'elastic:merge {entityType}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Merge forcefully ES index by specified ES entity type';

    /**
     * @param EntityResourceModelMapperInterface $helper
     *
     * @return void
     */
    public function handle(EntityResourceModelMapperInterface $helper)
    {
        $entityType = $this->argument('entityType');
        if (!empty($entityType)) {

            $this->forceMerge($entityType, $helper);
        }
    }

    /**
     * @param string $entityType
     * @param EntityResourceModelMapperInterface   $helper
     */
    protected function forceMerge(string $entityType, EntityResourceModelMapperInterface $helper)
    {
        try {
            // @todo maintenance mode is now local and thus cannot be trusted across servers
            $exitCode = Artisan::call('app:maintenance', [
                'mode'        => 'enable',
                '--read-only' => true
            ]);
            if ($exitCode !== 0) {
                throw new \Exception('Could not enable maintenance mode');
            }
            $this->log($entityType . ': Read Only Maintenance mode enabled');

            $exitCode = Artisan::call('queue:processor:kill', [
                '--force' => true,
            ]);

            if ($exitCode !== 0) {
                Artisan::call('app:maintenance', [
                    'mode' => 'disable'
                ]);
                throw new \Exception('Could not kill queue processors');
            }

            $this->log($entityType . ': Queue processors killed');
            $this->log($entityType . ': Maintenance mode graceful waiting...');

            $this->output->progressStart(self::MAINTENANCE_MODE_GRACEFUL_TIMEOUT);
            for ($i = 0; $i < self::MAINTENANCE_MODE_GRACEFUL_TIMEOUT; $i++) {
                sleep(1);

                $this->output->progressAdvance();
            }
            $this->output->progressFinish();

            $this->log($entityType . ': Wait completed');

            $resourceModelClass = $helper->getResourceModelClassNameByEntityType($entityType);

            if (!$resourceModelClass || !class_exists($resourceModelClass)) {
                throw new \Exception('Entity "' . $entityType . '" is not defined (resource model does not exists)');
            }
            /** @var AbstractElasticsearchResourceModel $resourceModel */
            $resourceModel = app()->make($resourceModelClass);
            $time = microtime(true);

            $alias = $resourceModel->getWriteAlias();
            $result = $resourceModel->getAdapter()->forceMergeIndex($alias);

            $this->log(sprintf(
                    '%s: ForceMerge completed, alias was "%s", took %s s',
                    $entityType,
                    $alias,
                    microtime(true) - $time)
            );
            $this->log($entityType . ': Results: ' . print_r($result, true));

            $exitCode = Artisan::call('app:maintenance', [
                'mode' => 'disable'
            ]);
            if ($exitCode !== 0) {
                throw new \Exception('Could not disable maintenance mode');
            }
            $this->log($entityType . ': Maintenance mode disabled');
        } catch (\Throwable $e) {
            $this->log($e);
            Artisan::call('app:maintenance', [
                'mode' => 'disable'
            ]);
        }
    }
}