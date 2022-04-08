<?php
declare(strict_types=1);

namespace Levtechdev\SimPaas\Service\Logger\Monolog\Handler;

use Aws\Sqs\SqsClient;
use Monolog\Logger;
use Monolog\Handler\AbstractProcessingHandler;

class SqsHandler extends AbstractProcessingHandler
{
    /** 256 KB in bytes - maximum message size in SQS */
    protected const MAX_MESSAGE_SIZE = 262144;

    /** @TODO SqsClient is not in composer.json yet */
    /** @var SqsClient */
    private $client;

    /** @var string */
    private $queueUrl;

    public function __construct(SqsClient $sqsClient, string $queueUrl, $level = Logger::DEBUG, bool $bubble = true)
    {
        parent::__construct($level, $bubble);

        $this->client = $sqsClient;
        $this->queueUrl = $queueUrl;
    }

    /**
     * Writes the record down to the log of the implementing handler.
     *
     * @param array $record
     */
    protected function write(array $record): void
    {
        $context = $record['context'];
        $messageBody = json_encode($context);
        if (strlen($messageBody) >= static::MAX_MESSAGE_SIZE) {
            $context['trace'] = 'truncated';
            $messageBody = json_encode($context);
        }
        $this->client->sendMessage([
            'QueueUrl'       => $this->queueUrl,
            'MessageBody'    => $messageBody,
        ]);
    }
}