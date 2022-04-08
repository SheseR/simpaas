<?php
declare(strict_types=1);

namespace Levtechdev\SimPaas\Helper;

use Levtechdev\SimPaas\Service\Logger\Monolog\Formatter\JsonFormatter;
use Exception;
use Illuminate\Contracts\Support\Arrayable;
use Levtechdev\SimPaas\Service\Logger\Monolog\Formatter\InlineFormatter;
use Levtechdev\SimPaas\Service\Logger\Monolog\Formatter\PrintrFormatter;
use Levtechdev\SimPaas\Service\Logger\Monolog\Formatter\XmlFormatter;
use Levtechdev\SimPaas\Service\Logger\Monolog\Processor\LogProcessor;
use Monolog\Handler\HandlerInterface;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Processor\MemoryUsageProcessor;
use Monolog\Processor\TagProcessor;

class Logger extends Core
{
    const LOGS_DIR           = 'storage' . DS . 'logs' . DS;
    const RAW_LOGS_DIR       = 'logs' . DS . 'raw' . DS;
    const ROTATING_MAX_FILES = 7;

    /**
     * Log file supported formats
     */
    const LOG_FILE_FORMAT_PRINT_R = 'print_r';
    const LOG_FILE_FORMAT_JSON    = 'json';
    const LOG_FILE_FORMAT_XML     = 'xml';
    const LOG_FILE_FORMAT_INLINE  = 'inline';

    /**
     * Log data verbosity levels
     */
    const LOG_VERBOSITY_LEVEL_MINIMAL  = 'minimal';
    const LOG_VERBOSITY_LEVEL_MODERATE = 'moderate';
    const LOG_VERBOSITY_LEVEL_VERBOSE  = 'verbose';

    /**
     * Logger IMS tag
     */
    const LOGGER_IMS_TAG = 'ims';

    /**
     * Inline log formatting settings
     */
    const LOGGER_INLINE_OUTPUT_FORMAT      = '%datetime% > %channel% > %level_name% > %message% %context% %extra%';
    const LOGGER_INLINE_OUTPUT_DATE_FORMAT = 'Y n j, g:i a';

    /**
     * List of array keys not to be captured in Loger::getSystemDebugData()
     *
     * @var array
     */
    protected $debugRemoveKeys = [];

    /**
     * List of $_REQUEST keys to be obfuscated in Logger::getSystemDebugData()
     *
     * @var array
     */
    protected $debugFilterDataKeys = [];

    /**
     * List of $_COOKIE keys to be obfuscated in Logger::getSystemDebugData()
     *
     * @var array
     */
    protected $debugFilterCookieKeys = [];

    /**
     * Cache for Monolog\Logger instances based on channel and log file name
     *
     * @var array
     */
    protected $loggers = null;

    protected $currentVerbosityLevel;
    protected $currentLogFormat;

    /**
     * @var array
     */
    protected $cachedDebugData = [];

    protected $environment;
    protected $slackWebhookUrl;

    /**
     * Logger constructor.
     *
     * @param array $config
     */
    public function __construct(array $config)
    {
        $this->currentVerbosityLevel = $config['verbosity_level'];
        $this->debugFilterDataKeys = $config['debug_filter_data_keys'];
        $this->debugFilterCookieKeys = $config['debug_filter_cookie_keys'];
        $this->debugRemoveKeys = $config['debug_remove_keys'];
        $this->currentLogFormat = $config['format'];
        $this->environment = env('APP_ENV', SystemInfo::APP_ENV_DEV);
        $this->slackWebhookUrl = env('LOG_SLACK_WEBHOOK_URL');
    }

    /**
     * Initialize Monolog logger and retrieve logger instance
     *
     * @param string $channel
     * @param string $logFile
     *
     * @return \Monolog\Logger
     *
     * @throws Exception
     */
    public function getLogger(string $channel, string $logFile)
    {
        $filNameMD5 = md5($logFile);

        if (!isset($this->loggers[$channel][$filNameMD5])) {
            $logger = new \Monolog\Logger($channel);
            $this->loggers[$channel][$filNameMD5] = $logger;

            $handler = new RotatingFileHandler($logFile, self::ROTATING_MAX_FILES);
            $this->setHandlerFormatter($handler);
            $logger->pushHandler($handler);

            $this->setLoggerProcessors($logger);
        }

        return $this->loggers[$channel][$filNameMD5];
    }

    /**
     * Set logger processors
     *
     * @param \Monolog\Logger $logger
     *
     * @return $this
     */
    protected function setLoggerProcessors($logger)
    {
        if (!($logger instanceof \Monolog\Logger)) {

            return $this;
        }

        $verbosityLevel = $this->getConfigLogVerbosityLevel();

        if ($verbosityLevel == static::LOG_VERBOSITY_LEVEL_MINIMAL) {

            return $this;
        }

        $moderateLevels = [static::LOG_VERBOSITY_LEVEL_MODERATE, static::LOG_VERBOSITY_LEVEL_VERBOSE];
        if (in_array($verbosityLevel, $moderateLevels)) {
            $logger->pushProcessor(new MemoryUsageProcessor());
            $logger->pushProcessor(new TagProcessor(array(self::LOGGER_IMS_TAG)));
        }

        if ($verbosityLevel == static::LOG_VERBOSITY_LEVEL_VERBOSE) {
            $debugFlag = env('API_REQUEST_DATA_DEBUG', false);
            $logger->pushProcessor(new LogProcessor(false, $debugFlag));
        }

        return $this;
    }

    /**
     * Set handler formatter
     *
     * @param HandlerInterface $handler
     */
    protected function setHandlerFormatter($handler)
    {
        if ($handler instanceof HandlerInterface) {
            $handler->setFormatter($this->getFormatter());
        }
    }

    /**
     * Retrieve formatter instance based on system configuration settings
     *
     * @return \Monolog\Formatter\FormatterInterface|null
     */
    protected function getFormatter()
    {
        $logFormat = $this->getConfigLogFormat();
        $formatter = null;
        switch ($logFormat) {
            case self::LOG_FILE_FORMAT_JSON:
                $formatter = new JsonFormatter();
                break;
            case self::LOG_FILE_FORMAT_XML:
                $formatter = new XmlFormatter();
                break;
            case self::LOG_FILE_FORMAT_PRINT_R:
                $formatter = new PrintrFormatter();
                break;
            case self::LOG_FILE_FORMAT_INLINE:
            default:
                $formatter = new InlineFormatter(
                    self::LOGGER_INLINE_OUTPUT_FORMAT,
                    self::LOGGER_INLINE_OUTPUT_DATE_FORMAT
                );
                break;
        }

        return $formatter;
    }

    /**
     * Retrieve log format
     *
     * @return string
     */
    public function getConfigLogFormat()
    {
        return $this->currentLogFormat;
    }

    /**
     * Retrieve log verbosity level
     *
     * @return string
     */
    public function getConfigLogVerbosityLevel()
    {
        return $this->currentVerbosityLevel;
    }

    /**
     * Collect system information for debug purposes
     *
     * @param bool $includeStacktraces
     * @param bool $includeRequestParams
     *
     * @return array
     */
    public function getSystemDebugData($includeStacktraces = true, $includeRequestParams = true)
    {
        $cacheKey = $includeStacktraces ? 'trace-true' : 'trace-false';
        $cacheKey .= $includeRequestParams ? 'params-true' : 'params-false';
        if (key_exists($cacheKey, $this->cachedDebugData)) {

            return $this->cachedDebugData[$cacheKey];
        }

        /** @var SystemInfo $systemInfo */
        $systemInfo = app(SystemInfo::class);

        $debugData = array(
            'host'          => $systemInfo->getHostname(),
            'server_ip'     => $systemInfo->getServerIp(),
            'process_id'    => $systemInfo->getProcessId(),
            'unique_id'     => $systemInfo->getUniqid(),
            'env'           => $this->environment,
            'app'           => SystemInfo::APP_NAME,
            'client_id'     => $systemInfo->getApiClientId(),
            'user_id'       => $systemInfo->getApiUserId(),
            'build_version' => $systemInfo->getBuildVersion(),
        );

        if ($includeStacktraces) {
            $debugData['trace'] = $systemInfo->getDebugBackTrace();
        }

        if ($systemInfo->isCli()) {
            $debugData['argv'] = $_SERVER['argv'];

            return $debugData;
        }

        $serverData = [
            'request_path'  => $systemInfo->getPath(),
            'module'        => $systemInfo->getModule(),
            'route'         => $systemInfo->getRoute(),
            'referrer'      => $systemInfo->getHttpReferer(),
            'url'           => $systemInfo->getUrl(),
            'http_method'   => $systemInfo->getHttpMethod(),
            'http_protocol' => $systemInfo->getHttpProtocol(),
            'user_agent'    => $systemInfo->getUserAgent(),
            'http_via'      => $systemInfo->getHttpVia(),
            'remote_ip'     => $systemInfo->getRemoteIp(),
            'is_ajax'       => $systemInfo->isAjax(),
        ];

        $debugData = array_merge($debugData, $serverData);

        if ($includeRequestParams) {
            // Filter headers
            $headers = $systemInfo->getHeaders();
            if (!empty($headers)) {
                $headers = $this->filterDebugData($headers, $this->debugFilterDataKeys);
            }

            // Filter params
            $params = $systemInfo->getRequestParams();
            if (!empty($params)) {
                $params = $this->filterDebugData($params, $this->debugFilterDataKeys);
            }

            // Filter cookies
            $cookies = $_COOKIE;
            if (!empty($cookies)) {
                $cookies = $this->filterDebugData($cookies, $this->debugFilterCookieKeys);
                $cookies = $this->filterDebugData($cookies, $this->debugRemoveKeys, true);
            }

            $serverData = [
                'params'  => $params,
                'cookies' => $cookies,
                'headers' => $headers,
            ];

            $debugData = array_merge($debugData, $serverData);
        }

        $this->cachedDebugData[$cacheKey] = $debugData;

        return $this->cachedDebugData[$cacheKey];
    }

    /**
     * Normalize data
     * Recursive filter data by private conventions
     *
     * @param mixed $data           data array to filter
     * @param array $keys           keys to filter
     * @param bool  $remove         true to remove filtered keys, false to filter the value
     * @param bool  $mergeKeys      true to merge the data with known filters, false to overwrite
     * @param bool  $allowLargeData true will not cut debug array by 1000 limit
     *
     * @return array
     */
    public function filterDebugData(
        $data,
        array $keys = array(),
        $remove = false,
        $mergeKeys = false,
        $allowLargeData = false
    ) {
        $data = $this->normalizeData($data, $allowLargeData);

        if (!is_array($data)) {
            return $data;
        }

        if (empty($keys)) {
            $keys = $this->debugFilterDataKeys;
        } elseif ($mergeKeys) {
            $keys = array_unique(array_merge($keys, $this->debugFilterDataKeys));
        }

        return $this->applyDataFilter($data, $keys, (bool)$remove);
    }

    /**
     * Recursively apply data filters to an xD-array of data
     *
     * @param mixed $data   data array to filter
     * @param array $keys   keys to filter
     * @param bool  $remove true to remove filtered keys, false to filter the value
     *
     * @return array
     */
    protected function applyDataFilter(array $data, array $keys, $remove = false)
    {
        foreach ($data as $key => $value) {
            if (in_array($key, $keys, true)) {
                if ($remove) {
                    unset($data[$key]);
                } elseif ($value === null) {
                    $data[$key] = '**NULL**';
                } elseif (is_string($value) && $value === '') {
                    $data[$key] = '**EMPTY**';
                } else {
                    $data[$key] = '****';
                }
            } elseif (is_bool($data[$key])) {
                $data[$key] = (int)$data[$key];
            } elseif (is_array($data[$key])) {
                $data[$key] = $this->applyDataFilter($data[$key], $keys, $remove);
            }
        }

        return ($data);
    }

    /**
     * @param Exception|\stdClass|array|int|float|string|DataObject|bool|null $data
     * @param false                                                            $allowLargeData
     *
     * @return array|float|mixed|string
     */
    public function normalizeData($data, $allowLargeData = false)
    {
        if ($data instanceof \stdClass) {
            return $this->normalizeData((array)$data);
        } elseif (is_array($data) || $data instanceof Arrayable) {
            $result = array();
            $count = 1;
            foreach ($data as $k => $v) {
                if ($count++ >= 1000 && !$allowLargeData) {
                    $result['...'] = 'Over 1000 items, aborting normalization';
                    break;
                }
                $result[$k] = $this->normalizeData($v);
            }
        } elseif ($data instanceof DataObject) {
            $result = $this->normalizeData($data->getData());
            array_unshift($result, spl_object_hash($data));
            array_unshift($result, get_class($data));
        } elseif ($data instanceof \Throwable) {
            $result = [
                'class'   => get_class($data),
                'message' => $data->getMessage(),
                'code'    => $data->getCode(),
                'file'    => $data->getFile() . ':' . $data->getLine(),
                'trace'   => $data->getTraceAsString(),
            ];
        } elseif (is_resource($data)) {
            $result = sprintf('[resource] (%s)', get_resource_type($data));
        } else {
            if (is_null($data)) {
                $result = 'null';
            } elseif (is_float($data)) {
                if (is_infinite($data)) {
                    $result = ($data > 0 ? '' : '-') . 'INF';
                } elseif (is_nan($data)) {
                    $result = 'NaN';
                } else {
                    $result = $data;
                }
            } else {
                $result = $data;
            }
        }

        return $result;
    }

    /**
     * @param string|array|int|null|\Throwable $message
     * @param int $level
     *
     * @return bool
     */
    public function notifySlack($message, $level = \Monolog\Logger::ERROR)
    {
        if (($this->environment != SystemInfo::APP_ENV_PROD && $this->environment != SystemInfo::APP_ENV_STAGE)
            || !$this->slackWebhookUrl
        ) {
            return false;
        }

        try {
            /** @var \Illuminate\Log\Logger $logger */
            $logger = app('log')->channel('slack');
            $logger->log(
                strtolower(\Monolog\Logger::getLevelName($level)),
                $message,
                ['app' => SystemInfo::APP_NAME, 'env' => $this->environment]
            );
        } catch (\Throwable $e) {
            // ignore errors for now
            return false;
        }

        return true;
    }
}
