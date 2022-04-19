<?php

namespace Levtechdev\Simpaas\Console\Command\Management;

use Levtechdev\Simpaas\Authorization\Model\User;
use Levtechdev\Simpaas\Authorization\Repository\UserRepository;
use Levtechdev\Simpaas\Helper\Core;
use Levtechdev\Simpaas\Console\Command\AbstractCommand;
use Levtechdev\Simpaas\Authorization\Migration\SampleData;

class AppInitCommand extends AbstractCommand
{
    /**
     * @var string
     */
    protected $signature = 'app:init {--E|initEnv} {--U|initUsers} {--S|initSampleData} {--Nt : Do not show tokens list when --initUsers is used}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Initialize Simpaas application: set .env, create auth roles/rules/users';

    /** @var Core  */
    protected Core $coreHelper;

    /**
     * @param Core $coreHelper
     * @param UserRepository $userRepository
     *
     * @return void
     */
    public function handle(Core $coreHelper, UserRepository $userRepository)
    {
        $this->coreHelper = $coreHelper;

        $ranFlag = false;
        if ($this->option('initEnv')) {
            $this->initEnv();
            $ranFlag = true;
        }

        if ($this->option('initSampleData') || $this->option('initUsers')) {
            $this->addSampleData();
            $ranFlag = true;
            $this->showUserTokenList($userRepository);
        }

        if (!$ranFlag) {
            $this->warn('App initialization did not run - nothing was instructed to be done');
        } else {
            $this->info('App initialization completed');
        }
    }

    /**
     * Copy env.example to env
     */
    public function initEnv()
    {
        exec(sprintf('cp %s %s', base_path() . '/.env.example', base_path() . '/.env'));
        $this->info('Set .env config file');
    }

    public function addSampleData()
    {
        $this->info('Sample data processing started');
        $sampleDataModels = [
            SampleData::class,
            // @todo implement SimpleData retrieving from all modules here
        ];

        if ($this->option('initUsers')) {
            $this->info('Install users mode only');
            $installModel = app()->make(SampleData::class);
            if ($installModel) {
                $installModel->install();
                $this->info('Installed ' . get_class($installModel));
            }

            $sampleDataModels = [];
        }

        foreach ($sampleDataModels as $installModel) {
            $installModel = app()->make($installModel);
            if ($installModel) {
                $installModel->install();
                $this->info('Installed ' . get_class($installModel));
            }
        }

        $this->info('Sample data installed');

        return $this;
    }

    /**
     * @param UserRepository $repository
     */
    protected function showUserTokenList(UserRepository $repository)
    {
        if ($this->option('Nt')) {
            return;
        }
        $userCollection = $repository->getList([]);

        $res = [];
       /** @var User $user */
        foreach($userCollection as $user) {
            $res[] = [
                $user->getId(),
                $user->getClientId(),
                $user->getToken()
            ];
        }

        $this->table(['id', 'client_id', 'token'], $res);
    }
}
