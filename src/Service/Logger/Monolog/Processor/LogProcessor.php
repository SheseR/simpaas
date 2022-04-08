<?php

namespace Levtechdev\SimPaas\Service\Logger\Monolog\Processor;

use Monolog\Logger as Monolog;
use Levtechdev\SimPaas\Helper\Logger;
use Levtechdev\SimPaas\Middleware\Http\LoggerMiddleware;

class LogProcessor
{
    /**
     * System debug data cache storage
     *
     * @var array
     */
    protected array $systemDebugData = [];

    /**
     * Set flags to add trace and include request params
     * Collect system data
     *
     * @param bool $includeStacktraces
     * @param bool $includeRequestParams
     */
    public function __construct(bool $includeStacktraces = true, bool $includeRequestParams = true)
    {
        /** @var Logger $logger */
        $logger = app(Logger::class);
        $this->systemDebugData = $logger->getSystemDebugData($includeStacktraces, $includeRequestParams);
    }

    /**
     * Add system debug data to log record
     * Add stack trace to log record only if log level is not INFO
     *
     * @param array $record
     *
     * @return array
     */
    public function __invoke(array $record)
    {
        if (isset($record['extra'])) {
            $record['extra'] = array_merge($record['extra'], $this->systemDebugData);
        } else {
            $record['extra'] = $this->systemDebugData;
        }

        if ($record['level'] == Monolog::INFO) {
             unset($record['extra']['trace']);
        }

        // log request params only into central api log file
        if ($record['channel'] != LoggerMiddleware::DEFAULT_CHANNEL) {
            unset($record['extra']['params']);
        }

        return $record;
    }
}