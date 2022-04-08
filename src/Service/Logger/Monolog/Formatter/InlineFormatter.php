<?php
namespace Levtechdev\Simpaas\Service\Logger\Monolog\Formatter;

use Monolog\Formatter\LineFormatter;
use Monolog\Logger as Monolog;
use Levtechdev\Simpaas\Helper\Logger;

/**
 * Encodes whatever record data is passed to it as one line output
 */
class InlineFormatter extends LineFormatter
{
    /**
     * {@inheritdoc}
     */
    public function format(array $record): string
    {
        return parent::format($record) . "\n";
    }

    /**
     * Normalize message in given log $record or entire record if message was omitted
     *
     * @param mixed $record
     * @param int   $depth
     *
     * @return array|bool|float|int|string|string[]|null
     */
    protected function normalize($record, $depth = 0)
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
