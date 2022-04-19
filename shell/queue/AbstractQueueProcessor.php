<?php

use Levtechdev\Simpaas\Helper\Logger;

$app = require __DIR__ . DIRECTORY_SEPARATOR . '../../bootstrap/app.php';
$app->boot();

/**
 * @see https://blog.pascal-martin.fr/post/php71-en-other-new-things/
 *      https://stitcher.io/blog/asynchronous-php
 *      https://github.com/brianlmoon/GearmanManager/issues/150#issuecomment-409722838
 */
pcntl_async_signals(true);

$signal = 0;
$handler = function ($signo) {
    global $signal;
    $signal = $signo;
};

pcntl_signal(\SIGINT, $handler);
pcntl_signal(\SIGTERM, $handler);

/**
 * Class ProcessorCommand
 *
 * @package App\Core\Console\Queue
 */
abstract class AbstractQueueProcessor
{
    use Levtechdev\Simpaas\Queue\RabbitMq\Command\Processor\EnabledQueueTrait;

    const BASE_PATH_WORKERS           = 'shell' . DS . 'queue' . DS . 'workers';
    const CONFIG_PATH_QUEUE_PROCESSOR = 'queue.rabbitmq.consumers.%s.processor';

    /**
     * @var array
     */
    protected $config;

    /**
     * @var bool
     */
    protected $autoScale = false;

    /**
     * @var int
     */
    protected $numWorkers = 1;

    /**
     * @var int
     */
    protected $maxNumWorkers = 1;

    /**
     * @var int
     */
    protected $cycleTime = 1;

    /**
     * @var int
     */
    protected $alertSize = 2;

    /**
     * @var int
     */
    protected $autoScaleMPW = 1;

    /**
     * @todo not used
     * @var bool
     */
    protected $debug;

    /**
     * @var string
     */
    protected $logFile;

    /**
     * @var int
     */
    protected $messageCount = 0;

    /**
     * @var int
     */
    protected $consumerCount = 0;

    /**
     * @var array
     */
    protected $workerPids = [];

    /**
     * @var int
     */
    protected $killSignal;

    /**
     * @var array
     */
    protected array $configProcessor = [];

    protected $queue;

    /**
     * @var string
     */
    protected $phpPath;


    public function __construct(protected \Levtechdev\Simpaas\Queue\RabbitMq\Container $container)
    {
//        $this->queue = $queue;
//
//        $this->configProcessor = app()->make('config')->get(
//            sprintf(self::CONFIG_PATH_QUEUE_PROCESSOR, $this->getConsumerAliasName())
//        );
        $this->phpPath = env('PHP_PATH', '/usr/local/bin/php');
    }

    /**
     * @return int
     */
    protected function isKill()
    {
        global $signal;

        $this->killSignal = $signal;
        $this->log('Current Signal is: ' . $this->killSignal);

        return $this->killSignal;
    }

    /**
     * @param string $queueName
     *
     * @return bool
     */
    protected function isEnabledQueue(string $queueName): bool
    {
        return in_array($queueName, $this->getEnabledQueues());
    }

    /**
     * @throws Exception
     */
    public function handle()
    {
        $beatCounter = 1;
        $consumerAliasName = $this->getConsumerAliasName();
        $queueEntity = $this->container->getConsumer($consumerAliasName);

        while (!$this->isKill()) {
            if (!$this->isEnabledQueue($queueEntity->getQueueAliasName())) {
                $this->log(sprintf(
                    'Queue %s is not active in local/queue/.enabled_queue list, terminating all workers...',
                    $queueEntity->getQueueAliasName())
                );

                break;
            }

            if (is_maintenance() || is_maintenance_rom()) {
                $this->log('Detected Maintenance Mode, terminating all workers...');

                break;
            }

            $this->refreshParams();

            $this->log('---------------Beat #' . $beatCounter . '---------------');
            $this->log("Memory Usage: " . number_format(memory_get_usage() / 1000000, 2) . " MB");

            try {
                list(, $this->messageCount, $this->consumerCount) = $queueEntity->getQueueInfo();
            } catch (\Exception $exception) {
                $this->log('Queue ' . $queueEntity->getQueueAliasName() . ' is not ready: ' . $exception->getMessage());

                break;
            }

            $this->startWorkers();

            if ($this->cycleTime >= 1) {
                $this->log("Sleeping $this->cycleTime seconds...");
                sleep($this->cycleTime);
            }

            $this->processFinishedWorkers();
            $beatCounter++;
        }

        $this->killWorkers();
        $this->log('Exiting');
        exit;
    }

    /**
     * Refresh queue processor parameters
     */
    protected function refreshParams(): void
    {
        // Reset queue config
        $path = app()->getConfigurationPath('queue');
        if ($path) {
            app()->make('config')->set('queue', require $path);
        }

        // Get fresh config values
        $this->configProcessor = app()->make('config')->get(
            sprintf(self::CONFIG_PATH_QUEUE_PROCESSOR, $this->getConsumerAliasName())
        );

        $this->autoScale = $this->configProcessor['options']['auto_scale'] ?? false;
        $this->autoScaleMPW = $this->configProcessor['options']['auto_scale_mpw'] ?? 1;
        $this->numWorkers = $this->configProcessor['options']['num_workers'] ?? 1;
        $this->maxNumWorkers = $this->configProcessor['options']['max_num_workers'] ?? 1;
        $this->cycleTime = (int)$this->configProcessor['options']['cycle_time'] ?? 1;
        $this->alertSize = $this->configProcessor['options']['alert_threshold_size'] ?? 10;
        $this->debug = $this->configProcessor['debug'] ?? false;

        $filename = $this->configProcessor['log_file'] ?? 'queue_processors.log';
        $directory = storage_path(Logger::RAW_LOGS_DIR);
        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }
        $this->logFile = $directory . $filename;
    }

    /**
     * Create worker processes and assign them queue items
     */
    protected function startWorkers()
    {
        $numNewWorkers = $this->getNumWorkers(
            $this->messageCount,
            $this->consumerCount,
            $this->numWorkers,
            $this->maxNumWorkers,
            $this->autoScale,
            $this->autoScaleMPW
        );

        if ($this->consumerCount > $this->alertSize && $this->consumerCount != $this->maxNumWorkers) {
            $this->log('ALERT! Workers count reaching maximum: ' . $this->maxNumWorkers);
        }


        $this->log(sprintf(
            'Status: Msgs=%s WrkCount=%s out of %s NewWrk=%s',
            $this->messageCount,
            $this->consumerCount,
            $this->maxNumWorkers,
            $numNewWorkers
        ));

        // create workers with item IDs assigned round-robin
        for ($i = 0; $i < $numNewWorkers; $i++) {
            $pid = $this->startWorkerInBackground();
            $this->workerPids[] = $pid;
            $this->log('Created new worker PID=' . $pid);
        }
    }

    /**
     * @param int $messageCount
     * @param int $consumerCount
     * @param int $numWorkers
     * @param int $maxNumWorkers
     * @param int $autoScale
     * @param int $autoScaleMPW
     *
     * @return int
     */
    public function getNumWorkers(
        int $messageCount,
        int $consumerCount,
        int $numWorkers,
        int $maxNumWorkers,
        int $autoScale,
        int $autoScaleMPW = 1
    ): int {
        if ($autoScale) {
            if ($messageCount == 0) {
                return $numWorkers - $consumerCount;
            }

            if ($messageCount / ($autoScaleMPW > 0 ?: 1) > ($maxNumWorkers - $consumerCount)) {
                return $maxNumWorkers - $consumerCount;
            }
        }

        return $consumerCount == 0 ? $numWorkers : 0;
    }

    /**
     * Start a background worker process and return the process ID
     *
     * @return integer
     */
    protected function startWorkerInBackground(): int
    {
        return (int)exec(
            sprintf('%s %s %s >> %s 2>&1 & echo $!',
                $this->phpPath,
                base_path(self::BASE_PATH_WORKERS . DS . $this->configProcessor['consumer']['worker']),
                implode(' ', $this->configProcessor['consumer']['arguments'] ?? []),
                $this->logFile
            )
        );
    }

    /**
     * Check if worker processes have exited
     */
    protected function processFinishedWorkers()
    {
        if (!is_array($this->workerPids)) {
            return $this;
        }

        foreach ($this->workerPids as $k => $pid) {
            if (!$this->isRunning($pid)) {
                unset($this->workerPids[$k]);
            }
        }

        return $this;
    }

    /**
     * Check if a process is running
     *
     * @see https://stackoverflow.com/questions/45953/php-execute-a-background-process/45966#45966
     *
     * @param int $pid
     *
     * @return bool
     */
    protected function isRunning($pid): bool
    {
        $result = shell_exec(sprintf('ps %d', $pid));

        return count(preg_split("/\n/", $result)) > 2;
    }

    /**
     * Pass the received signal to all workers and wait for them to exit
     */
    protected function killWorkers()
    {
        if (empty($this->workerPids)) {
            $this->log('Cannot kill workers, active workers list is empty');

            return;
        }

        foreach ($this->workerPids as $pid) {
            $this->killWorker($pid);
        }

        $this->log('Started Cleaning Worker PIDs');
        while ($this->workerPids) {
            $this->processFinishedWorkers();
            usleep(1000);
        }
        $this->log('Completed Cleaning Worker PIDs');
    }

    /**
     * @param $pid
     */
    protected function killWorker($pid)
    {
        $killSignal = $this->killSignal ?: SIGINT;
        $this->log(sprintf('Send graceful stop signal(%s) to worker PID %s', $killSignal, $pid));
        // we dont want to send KILL signal to its workers
        if (\function_exists('posix_kill')) {
            $ok = @posix_kill($pid, $killSignal);
        } elseif ($ok = proc_open(sprintf('kill -%d %d', $killSignal, $pid), [2 => ['pipe', 'w']], $pipes)) {
            $ok = false === fgets($pipes[2]);
        }

        if (!$ok) {
            $this->log(sprintf('Worker PID="%s" kill signal(%d) did not work', $pid, $killSignal));
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
     * @return string
     */
    abstract protected function getConsumerAliasName(): string;
}
