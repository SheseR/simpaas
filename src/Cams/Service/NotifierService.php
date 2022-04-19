<?php

namespace Levtechdev\Simpaas\Cams\Service;

use Levtechdev\Simpaas\Cams\Client\CamsClientAdapter;
use Levtechdev\Simpaas\Helper\Logger as LoggerHelper;
use Psr\Log\LoggerInterface;


/**
 * Class NotifierService
 *
 * @package App\Modules\Cams\Service
 */
class NotifierService
{
    const DESTINATION = 'cams-logs-stream';

    /** @var LoggerInterface|null */
    protected LoggerInterface|null $logger = null;

    public function __construct(
        protected CamsClientAdapter $camsAdapter,
        protected LoggerHelper $loggerHelper
    ) {

    }

    /**
     * @param LoggerInterface $logger
     * @return $this
     */
    public function setLogger(LoggerInterface $logger): self
    {
        $this->logger = $logger;

        return $this;
    }

    /**
     * @return LoggerInterface|null
     */
    public function getLogger(): LoggerInterface|null
    {
        return $this->logger;
    }

    /**
     * @param array $queueItems
     *
     * @return array
     * @throws \Throwable
     */
    public function process(array $queueItems): array
    {
        if (empty($queueItems)) {

            return [];
        }

        $t = microtime(true);
        $batch = [];
        try {
            $batch = $this->prepareBatch($queueItems);
            $this->camsAdapter->addRecords(static::DESTINATION, $batch);
            $this->getLogger()->debug(sprintf('Sent %s log entries in %ss', count($queueItems), microtime(true) - $t));

            return array_fill_keys(array_keys($queueItems), ['status' => true]);
        } catch (\Throwable $e) {
            $this->getLogger()->error($e, ['batch' => $batch]);
            $this->loggerHelper->notifySlack($e->getMessage(), \Monolog\Logger::ERROR);

            return array_fill_keys(array_keys($queueItems), ['status' => false]);
        }
    }

    /**
     * @param array $items
     *
     * @return array
     */
    protected function prepareBatch(array $items): array
    {
        $batch = [];
        foreach ($items as $key => $item) {
            unset($item['delivery_tag']);
            unset($item['priority']);

            $batch[] = $item;
        }

        return $batch;
    }
}
