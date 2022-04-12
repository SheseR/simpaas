<?php
namespace Levtechdev\Simpaas\Model\Elasticsearch;

interface EntityResourceModelMapperInterface
{
    /**
     * @param string $type
     *
     * @return string|false
     */
    public function getResourceModelClassNameByEntityType(string $type): string|false;

    /**
     * @return array
     */
    public function getInstalledResources(): array;
}