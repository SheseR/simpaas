<?php

namespace Levtechdev\Simpass\Console\Command;

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Levtechdev\Simpaas\Helper\Logger;
use Levtechdev\Simpaas\Helper\SystemInfo;

abstract class AbstractCommand extends Command
{
    const LOG_CHANNEL = 'commands';
    const LOG_FILE    = 'commands.log';

    protected SystemInfo $sysInfo;
    protected \Monolog\Logger $logger;
    protected Logger $logHelper;

    protected bool $maintenanceMode    = false;
    protected bool $maintenanceRomMode = false;

    /**
     * Base constructor.
     *
     * @param SystemInfo $systemInfo
     * @param Logger     $logHelper
     *
     * @throws \Exception
     */
    public function __construct(
        SystemInfo $systemInfo,
        Logger $logHelper
    ) {
        $this->logHelper = $logHelper;
        $this->logger = $this->logHelper->getLogger(
            self::LOG_CHANNEL,
            base_path(Logger::LOGS_DIR . static::LOG_FILE)
        );
        $this->sysInfo = $systemInfo;

        $this->maintenanceMode = is_maintenance();
        $this->maintenanceRomMode = is_maintenance_rom();

        parent::__construct();
    }

    protected function configure()
    {
        $definitions = $this->getNativeDefinition();
        $definitions->setOptions([new InputOption('force')]);
        $this->setDefinition($definitions);
    }

    /**
     * Execute the console command.
     *
     * @param InputInterface   $input
     * @param OutputInterface $output
     *
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($this->maintenanceMode && !$this->option('force')) {
            $this->warn('Cannot execute commands at this time: maintenance mode is ON');

            return 0;
        }
        if ($this->maintenanceRomMode) {
            $this->warn('Be aware that Read Only Maintenance mode is enabled at this moment!');
        }

        $result = $this->laravel->call([$this, 'handle']);

        return is_int($result) ? $result : 0;
    }

    /**
     * @deprecated use is_maintenance() directly
     * @return bool
     */
    protected function isMaintenanceModeOn()
    {
        return is_maintenance();
    }

    /**
     * @param string $optionName
     *
     * @return array
     */
    protected function getInputMultipleOptionValues(string $optionName): array
    {
        $inputValues = $this->option($optionName);
        if (empty($inputValues)) {

            return [];
        }

        $inputValues = array_map('trim', explode(',', $inputValues));
        if (empty($inputValues)) {

            return [];
        }

        return array_values(array_filter($inputValues));
    }

    /**
     * @param string|\Throwable $message
     */
    public function log(string|\Throwable $message): void
    {
        $processId = $this->sysInfo->getProcessId();
        $stringMessage = $message;
        if ($message instanceof \Throwable) {
            $stringMessage = $message->getMessage() . "\n\n" . $message->getTraceAsString();
        }

        $formattedMessage = sprintf('[%s] PID %s - %s',
            date('Y-m-d H:i:s'),
            $processId,
            $stringMessage
        );
        if ($message instanceof \Throwable) {
            $this->error($formattedMessage);
            $this->logger->error($message);
        } else {
            $this->info($formattedMessage);
            $this->logger->debug($stringMessage);
        }
    }
}
