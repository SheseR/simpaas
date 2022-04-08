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
    protected $signature = 'app:init {--E|initEnv} {--U|initUsers} {--S|initSampleData} {--Q|initQueues} {--rQ|reinitQueues} {--Nt : Do not show tokens list when --initUsers is used}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Initialize IMS application: set .env, create auth roles/rules/users, init RabbitMQ queues etc.';

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

//        if ($this->option('initQueues') || $this->option('reinitQueues')) {
//            $this->initQueues((bool)$this->option('reinitQueues'));
//            $ranFlag = true;
//        }

        if (!$ranFlag) {
            $this->warn('App initialization did not run - nothing was instructed to be done');
        } else {
            $this->info('App initialization completed');
        }
    }

//    /**
//     * @param bool $reinitQueue
//     */
//    protected function initQueues(bool $reinitQueue = false)
//    {
//        // init exchanges
//        $configExchanges = config('queue.rabbitmq.exchanges');
//
//        foreach ($configExchanges as $exchangeName => $configExchange) {
//            $exchangeClassName = $configExchange['manager']['class'] ?? null;
//            if (empty($exchangeClassName)) {
//                $this->error(sprintf('Manager for exchange %s is not defined in config', $exchangeName));
//
//                continue;
//            }
//            try {
//                /** @var AbstractExchangeManager $exchangeManager */
//                $exchangeManager = app()->make($exchangeClassName);
//
//                if (! $exchangeManager instanceof AbstractExchangeManager) {
//                    $this->error(sprintf('Manager for exchange %s must be type of AbstractExchangeManager', $exchangeName));
//
//                    continue;
//                }
//
//                if ($reinitQueue) {
//                    $exchangeManager->deleteExchange();
//                    $this->info(sprintf('Exchange "%s" is deleted by %s', $exchangeName, $exchangeClassName));
//                }
//
//                $exchangeManager->initExchange();
//                $this->info(sprintf('Exchange "%s" is initialized by %s', $exchangeName, $exchangeClassName));
//            } catch (\Throwable $e) {
//                $this->error(sprintf(
//                    'Exchange "%s" cannot be initialized by %s: %s',
//                    $exchangeName,
//                    $exchangeClassName,
//                    $e->getMessage()
//                ));
//            }
//        }
//
//        $configQueues = config('queue.rabbitmq.queues');
//
//        $this->info('Start init queues');
//
//        foreach ($configQueues as $queueName => $configQueue) {
//            if (empty($configQueue['manager']['class'])) {
//                $this->error(sprintf('Manager for queue %s is not defined in config', $queueName));
//
//                continue;
//            }
//
//            try {
//
//                $managerClass = $configQueue['manager']['class'];
//                $arguments = $configQueue['manager']['arguments'] ?? [];
//
//                if (empty($arguments)) {
//                    /** @var AbstractQueueManager $manager */
//                    $manager = app()->make($managerClass);
//                } else {
//                    /** @var AbstractQueueManager $manager */
//                    $manager = app()->make($managerClass, $arguments);
//                }
//
//                if ($reinitQueue) {
//                    $manager->deleteQueue();
//                    $this->info(sprintf('Queue "%s" deleted by %s', $queueName, $configQueue['manager']['class']));
//                }
//
//                $manager->initQueue();
//                $this->info(sprintf('Queue "%s" initialized by %s', $queueName, $configQueue['manager']['class']));
//            }catch (MethodNotAllowedException $e) {
//                // there is no queue for the manager, so scip it
//            }catch (\Throwable $e) {
//                $this->error(sprintf(
//                    'Queue "%s" cannot be initialized by %s: %s',
//                    $queueName,
//                    $configQueue['manager']['class'],
//                    $e->getMessage()
//                ));
//            }
//        }
//
//        $this->info('Completed initializing queues');
//    }

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
