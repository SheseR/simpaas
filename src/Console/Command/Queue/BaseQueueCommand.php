<?php

namespace Levtechdev\Simpaas\Console\Command\Queue;

use SimPass\Console\Command\AbstractCommand;

/**
 * Class BaseQueueCommand
 *
 * @package App\Core\Console\Command\Queue
 */
abstract class BaseQueueCommand extends AbstractCommand
{
    /**
     * @return  array
     */
    protected function getEnabledQueues(): array
    {
        $enabledQueues = [];
        $filePath = base_path('local/queue') . DIRECTORY_SEPARATOR . '.queue';
        if (file_exists($filePath)) {
            $fileContent = trim(file_get_contents($filePath));
            $enabledQueues = preg_split('/(\s*)*,+(\s*)*/', $fileContent);
        }

        return $enabledQueues;
    }
}
