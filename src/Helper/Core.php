<?php

namespace Levtechdev\Simpaas\Helper;

class Core
{
    const EXPORT_STORAGE_PATH = 'export';
    const BUFFER_MEMORY_LIMIT = 1500000;

    /**
     * Get current api version
     *
     * @return string
     */
    public function getApiVersion(): string
    {
        // @todo Required laravel/lumen-framework
        return config('global.api_version');
    }
}
