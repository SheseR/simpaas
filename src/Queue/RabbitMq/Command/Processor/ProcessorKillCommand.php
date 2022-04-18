<?php

namespace Levtechdev\Simpaas\Queue\RabbitMq\Command\Processor;

use Illuminate\Console\Command;

class ProcessorKillCommand extends Command
{
    use EnabledQueueTrait;

    /**
     * @var string
     */
    protected $signature = 'rabbitmq:queue:processors:kill {--queue=}';

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
        $configConsumers = config('queue.rabbitmq.consumers');
        $queueName = $this->option('queue');

        foreach ($configConsumers as $consumerAliasName => $consumerConfig) {
            if (!empty($queueName) && $consumerConfig['queue'] !== $queueName) {

                continue;
            }

            $this->killProcessor($consumerConfig['processor']['class']);
        }
    }

    /**
     * @param string $processorScript
     * @return void
     */
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
        } elseif ($ok = proc_open(sprintf('kill -%d %d', SIGINT, $pid), [2 => ['pipe', 'w']], $pipes)) {
            $ok = false === fgets($pipes[2]);
        }

        $this->log(sprintf(
            '%s - Sent kill (%s) signal for "%s" PID=%s',
            __CLASS__,
            SIGINT,
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
