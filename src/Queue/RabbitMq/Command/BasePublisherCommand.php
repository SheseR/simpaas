<?php

namespace Levtechdev\Simpaas\Queue\RabbitMq\Command;

use Illuminate\Console\Command;
use Levtechdev\Simpaas\Queue\RabbitMq\Container;

class BasePublisherCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rabbitmq:publish {publisher} {message}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Publish one message';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(Container $container)
    {
        $container
            ->getPublisher($this->input->getArgument('publisher'))
            ->publish(['body' => $this->input->getArgument('message')]);
    }
}