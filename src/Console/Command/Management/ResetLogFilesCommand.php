<?php declare(strict_types=1);

namespace Levtechdev\SimPaas\Console\Command\Management;

use SplFileInfo;
use Symfony\Component\Finder\Finder;
use SimPass\Console\Command\AbstractCommand;

class ResetLogFilesCommand extends AbstractCommand
{
    /** @var string  */
    protected $signature = 'app:logs:reset
        {--path= : relative path to logs that must be reset. By default it is set to ./storage/logs/}
        {--read-only : Display log files to be rest, but do not execute actual reset}
    ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reset *.log files (empty out their content only)';

    /**
     * @param Finder $finder
     *
     * @return void
     */
    public function handle(Finder $finder): void
    {
        $optionDir = $this->option('path') ?? '';
        $onlyRead = $this->option('read-only') ?? false;

        $logsDir = storage_path('logs' . DS . $optionDir);

        $finder->files()
            ->in($logsDir)
            ->sortByName()
            ->name('*.log');

        // in default finding files only in logs directory
        if (empty($optionDir)) {
            $finder->depth(0);
        }

        if (!$finder->hasResults()) {
            $this->info(sprintf('Directory %s has no *.log files', $logsDir));

            return;
        }

        $this->info(sprintf('Started cleaning %d log files...', $finder->count()));

        $result = [];
        /** @var SplFileInfo $file */
        foreach ($finder as $file) {
            if ($onlyRead) {
                $result[] = [$file->getFilename(), human_file_size($file->getSize())];

                continue;
            }

            $result = exec(sprintf("echo '' > %s 2>&1 & echo $!", escapeshellarg($file->getRealPath())), $output);
            $this->line(sprintf('<fg=green>%s reset successfully</>', $file->getFilename()));

            if ($result) {
                continue;
            }

            $this->line(sprintf('<fg=red>%s was skipped. Reason: %s</>',
                $file->getFilename(),
                print_r($output, true)
            ));
        }

        if ($onlyRead) {
            $this->table(['Name', 'Size'], $result);
        }

        $this->info('Completed processing.');
    }
}
