<?php

namespace Levtechdev\Simpaas\Queue\RabbitMq\Command\Processor;

use Illuminate\Console\Command;
use Levtechdev\Simpaas\Helper\Logger;

class ProcessorCronCommand extends Command
{
    use EnabledQueueTrait;

    const BASE_PATH_PROCESSORS = 'shell/queue';
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'rabbitmq:queue:processors:run';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Running queue workers via processors';

    /**
     * @var array
     */
    protected $config;

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
        foreach ($this->config['consumers'] as $consumerAliasName => $consumerConfig) {
            if (!in_array($consumerConfig['queue'], $enabledQueues) || empty($consumerConfig['processor']['script'])) {

                continue;
            }

            $processorScriptFile = $consumerConfig['processor']['script'];
            $logFile = $directory . ($consumerConfig['processor']['log_file'] ?? 'queue_processors.log');

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
     * @param $message
     */
    public function log($message)
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
