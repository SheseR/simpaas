<?php

namespace Levtechdev\Simpaas\Queue\RabbitMq\Command;

use Illuminate\Console\Command;
use Levtechdev\Simpaas\Queue\RabbitMq\Container;
use Levtechdev\Simpaas\Queue\RabbitMQ\Entity\AMQPEntityInterface;
use Levtechdev\Simpaas\Queue\RabbitMq\Entity\ExchangeEntity;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableSeparator;

class ListEntitiesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rabbitmq:list';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List all entities by type: producers|consumers';

    /**
     * @var Container
     */
    protected $container;

    /**
     * Execute the console command.
     */
    public function handle(Container $container)
    {
        $this->container = $container;

        $table = new Table($this->output);
        $table->setHeaders(array('#', 'Type', 'Name', 'Type', 'Route keys'));

        $rows = [];
        $nr = 1;
        // Publishers
        /**
         * @var  $publisherName
         * @var AMQPEntityInterface $entity
         */
        foreach ($this->container->getPublishers() as $publisherName => $entity) {
            $rows[] = [
                $nr,
                "<options=bold;fg=yellow>Publisher</>",
                $publisherName,
                (string)($entity instanceof ExchangeEntity) ? 'EXCHANGE' : 'QUEUE',
                implode(', ', $this->getRouteKeys($entity)),

            ];
            $nr++;
        }
        $rows[] = new TableSeparator();
        // Consumers
        foreach ($this->container->getConsumers() as $publisherName => $entity) {
            $rows[] = [
                $nr,
                "<options=bold;fg=cyan>Consumer</>",
                $publisherName,
                '',
                ''
            ];
            $nr++;
        }
        $table->setRows($rows);
        $table->render();
    }

    /**
     * @param AMQPEntityInterface $publisher
     *
     * @return array
     */
    protected function getRouteKeys(AMQPEntityInterface $publisher): array
    {
        $attribute = $publisher->getAttributes();
        $keys = [];
        foreach ($attribute['bind'] as $bind) {
            $keys[] = $bind['routing_key'];
            if (($bind['exchange'] ?? null) == 'amq.direct') {
                return [
                    $bind['routing_key']
                ];
            }
        }

        return $keys;
    }
}