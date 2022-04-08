<?php

namespace Levtechdev\Simpaas\Console\Command\Queue;

use Levtechdev\Simpaas\Helper\Logger;

class QueueCron extends BaseQueueCommand
{
    const BASE_PATH_PROCESSORS = 'shell/queue';
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'queue:processors:run';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Running queue workers via processors';

    /**
     * @var array
     */
    protected array $config;

    /**
     * For testing import products
     */
    public function handle()
    {
        if (is_maintenance() || is_maintenance_rom()) {
            $this->log('App is under maintenance mode! Skip running queue processors');

            return;
        }

        $phpPath = env('PHP_PATH', '/usr/local/bin/php');

        $return = null;
        $output = [];
        exec('pgrep -f [T]heIMSisHere', $output, $return);

        if ($return === 127) {
            $this->log('Cannot start queue processors: "pgrep" utility is not installed');

            return;
        }

        $directory = storage_path(Logger::RAW_LOGS_DIR);
        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        $this->config = config('queue.rabbitmq');
        $enabledQueues = $this->getEnabledQueues();

        foreach ($this->config['queues'] as $queueName => $queue) {
            if (!in_array($queueName, $enabledQueues) || empty($queue['processor'])) {

                continue;
            }

            if (!key_exists('status', $queue['processor']) || $queue['processor']['status'] === false) {

                continue;
            }

            $processorScriptFile = $queue['processor']['script'];
            $logFile = $directory . ($queue['processor']['log_file'] ?? 'queue_processors.log');

            $processorScript = base_path(self::BASE_PATH_PROCESSORS . DS . $processorScriptFile);
            $output = [];
            $return = null;
            $pid = exec(
                sprintf('pgrep -f %s', $this->getRegExpr($processorScriptFile)), $output, $return);


            if ($return === 127) {
                $this->log('Cannot start queue processor for ' . $processorScriptFile);
                continue;
            }

            if (!$pid && $return === 1) {
                $processorArguments = $queue['processor']['arguments'] ?? [];

                $pid = exec(
                    sprintf('%s %s %s >> %s 2>&1 & echo $!',
                        $phpPath,
                        $processorScript,
                        implode(' ', $processorArguments),
                        $logFile
                    )
                );

                $this->log('Executed processor "' . $processorScriptFile . '", PID is ' . $pid);
            } else {
                $this->log('Processor "' . $processorScriptFile . '" is already running at PID ' . $pid);
            }
        }
    }

    /**
     * @param mixed $message
     */
    public function log(mixed $message): void
    {
        debug($message);
    }

    /**
     * @param string $script
     *
     * @return string|string[]
     */
    private function getRegExpr(string $script): array|string
    {
        return str_replace('Queue', '[Q]ueue', $script);
    }
}
