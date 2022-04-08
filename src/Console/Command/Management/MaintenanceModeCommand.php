<?php
declare(strict_types=1);

namespace Levtechdev\SimPaas\Console\Command\Management;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use SimPass\Console\Command\AbstractCommand;

class MaintenanceModeCommand extends AbstractCommand
{
    const MAINTENANCE_MODE_ENABLE  = 'enable';
    const MAINTENANCE_MODE_DISABLE = 'disable';

    /**
     * @var string
     */
    protected $signature = 'app:maintenance {mode} {--r|read-only : Whether the maintenance mode is enabled as Read Only - so that all data retrieval API endpoints staying available }';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Enable/Disable maintenance mode';

    /**
     * @return void
     */
    public function handle(): void
    {
        $mode = $this->argument('mode');
        $rom = (bool) $this->option('read-only');

        if ($mode != self::MAINTENANCE_MODE_ENABLE && $mode != self::MAINTENANCE_MODE_DISABLE) {
            $this->error('Mode argument is not valid, must be "enable" or "disable"');

            return;
        }

        if ($mode == self::MAINTENANCE_MODE_ENABLE) {
            $this->enableMaintenanceMode($rom);
        } else {
            $this->disableMaintenanceMode();
        }
    }

    /**
     * @var bool $rom - Read only mode
     * Enable maintenance mode
     */
    protected function enableMaintenanceMode(bool $rom)
    {
        $maintenanceFlagFile = $rom ? constant('MAINTENANCE_ROM_FILE') : constant('MAINTENANCE_FLAG_FILE');

        $fileInfo = pathinfo($maintenanceFlagFile);
        exec(sprintf('mkdir -p %s && touch %s', $fileInfo['dirname'], $maintenanceFlagFile));

        $this->info($rom ? 'Read only Maintenance mode is enabled' : 'Maintenance mode is enabled');
    }

    /**
     * Disable maintenance mode
     */
    protected function disableMaintenanceMode()
    {
        $maintenanceFiles = [constant('MAINTENANCE_FLAG_FILE'), constant('MAINTENANCE_ROM_FILE')];
        foreach ($maintenanceFiles as $file) {
            if (file_exists($file)) {
                @exec('rm ' . $file);
            }
        }
        $this->info('Maintenance mode is disabled');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->maintenanceMode = false;

        return parent::execute($input, $output);
    }
}
