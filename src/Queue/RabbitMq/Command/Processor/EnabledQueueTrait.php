<?php

namespace Levtechdev\Simpaas\Queue\RabbitMq\Command;

trait EnabledQueueTrait
{
    public function getEnabledQueues(): array
    {
        $enabledQueues = [];
        $filePath = base_path('local/queue') . DIRECTORY_SEPARATOR . '.enabled_queue';
        if (file_exists($filePath)) {
            $fileContent = trim(file_get_contents($filePath));
            $enabledQueues = preg_split('/(\s*)*,+(\s*)*/', $fileContent);
        }

        return (array) $enabledQueues;
    }
}