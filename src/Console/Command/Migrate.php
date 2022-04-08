<?php
declare(strict_types=1);

namespace Levtechdev\Simpaas\Console\Command;

use Illuminate\Database\Console\Migrations\MigrateCommand;

class Migrate extends MigrateCommand
{
    const MIGRATIONS_GLOB_PATH = 'app' . DS . 'Modules' . DS . '%s' . DS . 'Migration';

    protected array $resolvedModules = [];
    protected array $migratedModules = [];
    protected array $migrations = [];

    /**
     * @return int
     */
    public function handle(): int
    {
        if (!$this->confirmToProceed()) {

            return 1;
        }

        $this->migrator->usingConnection($this->option('database'), function () {
            $this->prepareDatabase();

            // Next, we will check to see if a path option has been defined. If it has
            // we will use the path relative to the root of this installation folder
            // so that migrations may be run for any path within the applications.
            /**
             * @customization START
             */
            $this->migrator->setOutput($this->output);
            foreach ($this->getMigrationPaths() as $path) {
                $this->info(sprintf('Working on migrations at %s...', $path));
                $this->migrator->run($path, [
            /** @customization END */
                    'pretend' => $this->option('pretend'),
                    'step' => $this->option('step'),
                ]);
            }

            // Finally, if the "seed" option has been given, we will re-run the database
            // seed task to re-populate the database, which is convenient when adding
            // a migration and a seed at the same time, as it is only this command.
            if ($this->option('seed') && !$this->option('pretend')) {
                $this->call('db:seed', ['--force' => true]);
            }
        });

        return 0;
    }

    /**
     * @return array
     */
    protected function getMigrationPaths()
    {
        $baseDir = base_path() . DS;
        $modules = $this->getAllConfigFiles();

        foreach ($modules as $moduleName => $module) {
            $this->resolveDependencyRecursively($modules, $module, $moduleName);
        }

        $path = [];
        foreach ($this->migratedModules as $migratedModule) {
            $path[] = $baseDir . sprintf(self::MIGRATIONS_GLOB_PATH, $migratedModule);
        }

        return $path;
    }

    /**
     * @return array
     */
    protected function getAllConfigFiles(): array
    {
        $baseDir = base_path() . DS;
        $globPattern = 'app' . DS . 'Modules' . DS . '*' . DS . 'config' . DS . 'module.php';

        $modules = [];
        foreach (glob($baseDir . $globPattern, GLOB_NOSORT) as $path) {
            $temp = explode('/', str_replace('/config/module.php', '', $path));
            $moduleName = end($temp);

            $modules = array_merge($modules, [$moduleName => require $path]);
        }

        return $modules;
    }

    /**
     * @param     $modules
     * @param     $module
     * @param     $moduleName
     * @param int $recursionDepth
     */
    protected function resolveDependencyRecursively($modules, $module, $moduleName, int $recursionDepth = 0)
    {
        if ((!isset($module['depends']) || count($module['depends']) === 0) && !in_array($moduleName, $this->migratedModules)) {
            $this->resolvedModules[$moduleName] = true;
            $this->migratedModules[] = $moduleName;
        } else {
            if (isset($module['depends'])) {
                foreach ($module['depends'] as $dependency) {
                    if (!isset($modules[$dependency])) {
                        throw new \RuntimeException(sprintf('Module %s not found', $dependency));
                    }
                    if ($recursionDepth >= 1488) {
                        throw new \RuntimeException('Circular reference found');
                    }
                    if (!isset($this->resolvedModules[$dependency])) {
                        $this->resolveDependencyRecursively(
                            $modules,
                            $modules[$dependency],
                            $dependency,
                            ++$recursionDepth
                        );
                        $this->resolvedModules[$dependency] = true;
                    }
                }
            }

            if (!in_array($moduleName, $this->migratedModules)) {
                $this->migratedModules[] = $moduleName;
            }
        }
    }
}
