<?php

namespace Levtechdev\SimPaas\Middleware\Http;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Levtechdev\SimPaas\Helper\Logger;

class LoggerMiddleware
{
    const ACCESS_LOG_FILE = 'api_access.log';
    const DEFAULT_CHANNEL = 'api_request';

    /** @var Logger */
    protected Logger $loggerHelper;
    protected \Monolog\Logger $logger;

    public function __construct(Logger $loggerHelper)
    {
        $this->loggerHelper = $loggerHelper;
    }

    /**
     * @param Request $request
     * @param \Closure $next
     *
     * @return mixed
     */
    public function handle(Request $request, \Closure $next): mixed
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response)
    {
        try {
            $filePath = base_path(Logger::LOGS_DIR . self::ACCESS_LOG_FILE);
            $this->logger = $this->loggerHelper->getLogger(self::DEFAULT_CHANNEL, $filePath);

            $this->logger->pushProcessor(static function (array $record) use ($request) {
                $record['context']['request_hash'] = hash('sha1', spl_object_hash($request));

                return $record;
            });

            $path = $request->decodedPath();
            $query = $request->getQueryString();
            if (!empty($query)) {
                $path .= '/?' . $query;
            }

            $responseData = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
            $t = microtime(true);
            $processingTime = number_format($t - get_constant('LUMEN_START_TIME', $t), 5);

            if (!env('API_RESPONSE_DATA_DEBUG', false)) {
                $includeKeys = ['status' => true, 'error' => true, 'errors' => true, 'total' => true, 'pages' => true, 'aggregation' => true];
                foreach ($responseData as $key => $value) {
                    if (!key_exists($key, $includeKeys)) {
                        unset($responseData[$key]);
                    }
                }
            }
            if (extension_loaded('newrelic')) {
                newrelic_add_custom_parameter('processing_time', $processingTime);
                newrelic_add_custom_parameter('client_ip', $request->getClientIp());
            }

            $message = sprintf(
                '%s /%s from %s with %s payload processed in %ss',
                $request->method(),
                $path,
                $request->getClientIp(),
                human_file_size(mb_strlen($request->getContent())),
                $processingTime
            );
            $this->logger->debug($message, ['response' => $responseData]);
        } catch (\Throwable $e) {
            // ignore
        }
    }
}
