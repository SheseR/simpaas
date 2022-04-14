<?php
namespace Levtechdev\Simpaas\Queue\RabbitMq\Command;

use Illuminate\Console\Command;
use Levtechdev\Simpaas\Queue\RabbitMq\Container;
use Levtechdev\Simpaas\Queue\RabbitMQ\Entity\AMQPEntityInterface;
use Levtechdev\Simpaas\Queue\RabbitMQ\Entity\ExchangeEntity;
use Levtechdev\Simpaas\Queue\RabbitMq\PublisherInterface;
use PhpAmqpLib\Exception\AMQPProtocolChannelException;

class SetupCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rabbitmq:setup {--force}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create all queues, exchanges and binds that are defined in entities AND referenced to' .
    ' either a publisher or a consumer';

    /**
     * @var Container
     */
    private Container $container;

    /**
     * Execute the console command.
     */
    public function handle(Container $container)
    {
        $this->container = $container;

        $forceRecreate = $this->input->getOption('force');

        $hasErrors = false;
        /** @var ExchangeEntity $entity */
        foreach ($this->container->getPublishers() as $publisherName => $entity) {
            try {
                $this->createEntity($entity, 'publisher', $publisherName, $forceRecreate);
            } catch (AMQPProtocolChannelException $e) {
                $hasErrors = true;
                $this->output->error(
                    sprintf(
                        "Could not create entity %s for publisher [%s], got:\n%s",
                        (string)$entity->getAliasName(),
                        (string)$publisherName,
                        (string)$e->getMessage()
                    )
                );
                $entity->reconnect();
            }
        }

//        /** @var QueueEntity|ExchangeEntity $entity */
//        foreach ($this->container->getConsumers() as $publisherName => $entity) {
//            try {
//                $this->createEntity($entity, 'consumer', $publisherName, $forceRecreate);
//            } catch (AMQPProtocolChannelException $e) {
//                $hasErrors = true;
//                $this->output->error(
//                    sprintf(
//                        "Could not create entity %s for consumer [%s], got:\n%s",
//                        (string)$entity->getAliasName(),
//                        (string)$publisherName,
//                        (string)$e->getMessage()
//                    )
//                );
//                $entity->reconnect();
//            }
//        }
//
        $this->output->block("Create binds");
        /** @var PublisherInterface $entity */
        foreach ($this->container->getPublishers() as $publisherName => $entity) {
            try {
                $entity->bind();
                $this->output->writeln(
                    sprintf(
                        "Created bind <info>%s</info> for publisher [<fg=yellow>%s</>]",
                        (string)$entity->getAliasName(),
                        (string)$publisherName
                    )
                );
            } catch (\Exception $e) {
                $hasErrors = true;
                $this->output->error(
                    sprintf(
                        "Could not bind entity %s for publisher [%s], got:\n%s",
                        (string)$entity->getAliasName(),
                        (string)$publisherName,
                        (string)$e->getMessage()
                    )
                );
            }
        }
//
//        /** @var ConsumerInterface $entity */
//        foreach ($this->container->getConsumers() as $consumerAliasName => $entity) {
//            try {
//                $entity->bind();
//                $this->output->writeln(
//                    sprintf(
//                        "Bind entity <info>%s</info> for consumer [<fg=yellow>%s</>]",
//                        (string)$entity->getAliasName(),
//                        (string)$consumerAliasName
//                    )
//                );
//            } catch (\Exception $e) {
//                $hasErrors = true;
//                $this->output->error(
//                    sprintf(
//                        "Could not create bind %s for consumer [%s], got:\n%s",
//                        (string)$entity->getAliasName(),
//                        (string)$consumerAliasName,
//                        (string)$e->getMessage()
//                    )
//                );
//            }
//        }
        return (int)$hasErrors;
    }


    /**
     * @param AMQPEntityInterface $entity
     * @param string $type
     * @param string $resourceName
     * @param bool $forceRecreate
     */
    private function createEntity(
        AMQPEntityInterface $entity,
        string $type,
        string $resourceName,
        bool $forceRecreate = false
    ) {
        if (true === $forceRecreate) {
            $this->output->writeln(
                sprintf(
                    "Deleting <info>%s</info> <fg=yellow>%s</>",
                    (string)($entity instanceof ExchangeEntity) ?  'EXCHANGE' : 'QUEUE',
                    (string)$entity->getAliasName()
                )
            );
            $this->info('some case');
            $entity->delete();
        }

        $entity->create();
        $this->output->writeln(
            sprintf(
                "Created <info>%s</info> <fg=yellow>%s</> for %s [<fg=yellow>%s</>]",
                (string)($entity instanceof ExchangeEntity) ? 'EXCHANGE' : 'QUEUE' ,
                (string)$entity->getAliasName(),
                (string)$type,
                (string)$resourceName
            )
        );
    }
}