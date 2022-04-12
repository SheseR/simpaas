<?php
declare(strict_types=1);

namespace Levtechdev\Simpaas\Console\Command\Elasticsearch;

use Levtechdev\Simpaas\Console\Command\AbstractCommand;
use Levtechdev\Simpaas\Model\Elasticsearch\EntityResourceModelMapperInterface;

class EntitiesList extends AbstractCommand
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'elastic:entities:list';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List ElasticSearch based CDMS entities';

    /**
     * @param EntityResourceModelMapperInterface $helper
     */
    public function handle(EntityResourceModelMapperInterface $helper)
    {
        $resources = $helper->getInstalledResources();

        $res = [];
        foreach($resources as $entityType => $resourceModelClass) {
            $res[] = [
                $entityType,
                $resourceModelClass
            ];
        }

        $this->table(['entity_type', 'resource_model'], $res);
    }
}