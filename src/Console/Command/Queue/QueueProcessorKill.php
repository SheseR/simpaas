<?php

namespace Levtechdev\Simpaas\Console\Command\Queue;

class QueueProcessorKill extends BaseQueueCommand
{
    /**
     * @var string
     */
    protected $signature = 'queue:processor:kill {--queue=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Kill Queue Workers';

    /**
     *
     */
    public function handle(): void
    {
        $configQueues = config('queue.rabbitmq.queues');
        $queueName = $this->option('queue');

        $enabledQueue = $this->getEnabledQueues();
        if (empty($queueName)) {
            foreach ($configQueues as $row => $value) {
                //skip kill process if current queue is not run in this server
                if (!in_array($row, $enabledQueue)) {

                    continue;
                }

               $this->killProcessor($value['processor']['script']);
            }

            return;
        }

        if (!isset($configQueues[$queueName])) {
            $this->log(sprintf(
                '%s - Queue name %s is invalid Valid queues are: %s',
                __CLASS__,
                $queueName,
                implode(',', array_keys($configQueues))
            ));

            return;
        }

        if (in_array($queueName, $enabledQueue)) {
            $this->killProcessor($configQueues[$queueName]['processor']['script']);
        }
    }

    private function killProcessor(string $processorScript): void
    {
        $output = [];
        $return = null;
        $pid = (int)exec(
            sprintf('pgrep -f %s', $this->getRegExpr($processorScript)),
            $output,
            $return
        );

        if (empty($pid)) {
            $this->log(sprintf('%s - Nothing to kill, processor "%s" is not running', __CLASS__, $processorScript));

            return;
        }

        if (\function_exists('posix_kill')) {
            $ok = @posix_kill($pid, SIGTERM);
        } elseif ($ok = proc_open(sprintf('kill -%d %d', SIGTERM, $pid), [2 => ['pipe', 'w']], $pipes)) {
            $ok = false === fgets($pipes[2]);
        }

        $this->log(sprintf(
            '%s - Sent kill (%s) signal for "%s" PID=%s',
            __CLASS__,
            SIGTERM,
            $processorScript,
            $pid
        ));

        if (\function_exists('posix_kill')) {
            $lastErrorCode = posix_get_last_error();
            if ($lastErrorCode != 0) {
                $this->log(sprintf('%s - Processor "%s" could not be killed: %s', __CLASS__, $processorScript, posix_strerror($lastErrorCode)));
            }
        }

        if (!$ok) {
            $this->log(sprintf('%s - Processor "%s" kill signal did not work', __CLASS__, $processorScript));
        }
    }

    /**
     * @param string $className
     *
     * @return mixed
     */
    private function getRegExpr(string $className): array|string
    {
        return str_replace('Queue', '[Q]ueue', $className);
    }

    /**
     * @param mixed $message
     * @return void
     */
    public function log(mixed $message): void
    {
        debug($message);
    }
}
