<?php

namespace Levtechdev\Simpaas\Helper;

class Core
{
    const EXPORT_STORAGE_PATH = 'export';
    const BUFFER_MEMORY_LIMIT = 1500000;

    /**
     * @var array
     */
    protected array $entityResourceModelMap = [];

    /**
     * @param string $type
     *
     * @return bool|string
     */
    public function getResourceModelClassNameByEntityType(string $type): string|bool
    {
        return $this->entityResourceModelMap[$type] ?? false;
    }

    /**
     * @return array
     */
    public function getInstalledResources(): array
    {
        return $this->entityResourceModelMap;
    }

    /**
     * Get current api version
     *
     * @return string
     */
    public function getApiVersion()
    {
        // @todo Required laravel/lumen-framework
        return config('global.api_version');
    }
}
