<?php

namespace App\Modules\ElasticCatalog\UI\CLI\Command\Elastic;

use App\Core\Helper\Core;
use App\Core\Console\Command\Base as BaseCommand;
use App\Modules\ElasticCatalog\ResourceModel\ElasticModel;

/**
 * Class UpdateMapping
 *
 * @package App\Modules\ElasticCatalog\UI\CLI\Command
 */
class UpdateMapping extends BaseCommand
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'elastic:update-mapping';

    /** @var string  */
    protected $signature = 'elastic:update-mapping {entityType}';

    /**
     * @var string
     */
    protected $description = 'Update mapping of entity index in ElasticSearch';

    public function handle(Core $helper)
    {
        $entityType = $this->argument('entityType');
        if (empty($entityType)) {
            throw new \Exception('Entity type is not specified');
        }

        $this->updateIndexMapping($entityType, $helper);
    }

    /**
     * @param string $entityType
     * @param Core $helper
     */
    protected function updateIndexMapping($entityType, $helper)
    {
        try {
            $resourceModelClass = $helper->getResourceModelClassNameByEntityType($entityType);

            if (!$resourceModelClass || !class_exists($resourceModelClass)) {
                throw new \Exception('Entity "' . $entityType . '" is not defined (resource model does not exists)');
            }
            /** @var ElasticModel $resourceModel */
            $resourceModel = app()->make($resourceModelClass);
            $time = microtime(true);

            $indexNames = $resourceModel->updateIndexesMappings();

            $this->info(
                sprintf(
                    'Update index mapping ["%s"] for entity type "%s", took %s s',
                    implode(',', $indexNames),
                    $entityType,
                    microtime(true) - $time)
            );
        } catch (\Throwable  $e) {
            $message = sprintf('Cannot update index mapping- %s', $e->getMessage());
            $this->error($message);
        }
    }
}