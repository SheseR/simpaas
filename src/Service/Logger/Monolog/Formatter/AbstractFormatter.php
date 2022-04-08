<?php
namespace Levtechdev\Simpaas\Service\Logger\Monolog\Formatter;

use Monolog\Formatter\FormatterInterface;
use Monolog\Logger as Monolog;
use Levtechdev\Simpaas\Helper\Logger;

abstract class AbstractFormatter implements FormatterInterface
{
    /**
     * {@inheritdoc}
     */
    public function format(array $record)
    {
        /**
         * Custom timestamp root record key is required to be compatible with fluentd
         */
        $record['@timestamp'] = date('c');

        return $this->normalize($record);
    }

    /**
     * {@inheritdoc}
     */
    public function formatBatch(array $records)
    {
        foreach ($records as $key => $record) {
            $records[$key] = $this->format($record);
        }

        return $records;
    }

    /**
     * Normalize message in given log $record or entire record if message was omitted
     *
     * @param mixed $record
     *
     * @return mixed
     */
    protected function normalize(mixed $record): mixed
    {
        $allowLargeData = false;
        if ($record['level'] == Monolog::DEBUG) {
            $allowLargeData = true;
        }
        try {
            $record = app(Logger::class)->filterDebugData($record, [], false, false, false, $allowLargeData);
        } catch (\Throwable $e) {

        }

        return $record;
    }
}
