<?php
declare(strict_types=1);

namespace Levtechdev\SimPaas\Helper;

use Illuminate\Http\Request;
use Levtechdev\SimPaas\Authorization\Helper\Auth;
use Levtechdev\SimPaas\Authorization\Model\User;

class SystemInfo extends Core
{
    const MAX_PARAMS_SERIALIZED_LENGTH = 1048576;
    const APP_NAME                     = 'IMS';

    const APP_ENV_DEV   = 'dev';
    const APP_ENV_QA    = 'qa';
    const APP_ENV_STAGE = 'stage';
    const APP_ENV_PROD  = 'production';

    /**
     * @var Request
     */
    protected $request;


    protected Auth $auth;

    public function __construct()
    {
        $this->request = app()->make('request');

        // @todo need to find better solution
        // Auth has redis dependency,
        // so we cannot run artisan command without redis connection in this case, but need it for deployment flow
        if (!$this->isCli()) {
            $this->auth = app()->make(Auth::class);
        }
    }

    /**
     * Generate human readable nice debug back trace
     *
     * @param int $removeLastItemsCount
     *
     * @return string
     */
    public function getDebugBackTrace($removeLastItemsCount = 0)
    {
        $e = new \Exception();
        $trace = explode("\n", $e->getTraceAsString());
        // reverse array to make steps line up chronologically
        $trace = array_reverse($trace);
        array_shift($trace); // remove {main}
        array_pop($trace); // remove call to this method
        if ($removeLastItemsCount > 0) {
            for ($k = 0; $k < $removeLastItemsCount; $k++) {
                array_pop($trace);
            }
        }
        $length = count($trace);
        $result = array();

        for ($i = 0; $i < $length; $i++) {
            $result[] = ($i + 1) . '#' . substr($trace[$i], strpos($trace[$i], ' '));
        }

        return implode("\n    ", $result);
    }

    /**
     * @return array
     */
    public function getHeaders()
    {
        $headers = [];
        if (function_exists('apache_request_headers')) {
            $headers = apache_request_headers();
            unset($headers['Cookie']);
        } else {
            foreach ($_SERVER as $key => $value) {
                if (substr($key, 0, 5) == "HTTP_") {
                    $key = str_replace(" ", "-", ucwords(strtolower(str_replace("_", " ", substr($key, 5)))));
                    $headers[$key] = $value;
                }
            }
        }

        return $headers;
    }

    /**
     * @return bool
     */
    public function isCli()
    {
        return !isset($_SERVER['REQUEST_METHOD']);
    }

    /**
     * @return array
     */
    public function getRequestParams()
    {
        $params = $this->request->request->all();

        if (!env('API_REQUEST_DATA_DEBUG', false)) {
            return ['API_REQUEST_DATA_DEBUG' => 'DISABLED'];
        }

        $length = mb_strlen(serialize($params));
        if ($length < self::MAX_PARAMS_SERIALIZED_LENGTH) {

            return json_encode($params);
        }

        return ['REQUEST_PARAMS_ARE_TOO_LARGE' => $length];
    }

    /**
     * @return false|string
     */
    public function getBuildVersion()
    {
        $pathBuildVersion = base_path('.version');

        return file_exists($pathBuildVersion) ? file_get_contents($pathBuildVersion) : 'N/A';
    }

    /**
     * @return mixed
     */
    public function getArgv()
    {
        return $_SERVER['argv'];
    }

    /**
     * @return string
     */
    public function getHostname()
    {
        return gethostname();
    }

    /**
     * @return int
     */
    public function getProcessId()
    {
        return getmypid();
    }

    /**
     * @return string
     */
    public function getUniqid()
    {
        return uniqid();
    }

    /**
     * @return string
     */
    public function getSessionId()
    {
        return 'N/A';
//        return session_id();
    }

    /**
     * @return mixed
     */
    public function getHttpReferer()
    {
        return $this->request->server('HTTP_REFERER');
    }

    /**
     * @return mixed
     */
    public function getServerIp()
    {
        return $this->request->server('SERVER_ADDR');
    }

    /**
     * @return string|null
     */
    public function getRemoteIp()
    {
        $ip = $this->request->server('HTTP_X_FORWARDED_FOR');
        if (!empty($ip)) {
            $ip = trim(explode(',', $ip)[0]);
        }

        return $ip ? $ip : 'CLI';
    }

    /**
     * @return string
     */
    public function getUrl()
    {
        return $this->request->getBaseUrl() ? $this->request->fullUrl() : '';
    }

    /**
     * @return string
     */
    public function getPath()
    {
        return $this->request->decodedPath();
    }

    /**
     * @return string
     */
    public function getModule()
    {
        $module = 'Core';
        $route = $this->getRoute();
        if (!empty($route)) {
            $module = $route;
        }

        $module = str_replace('App\\Modules\\', '', $module);
        $parts = explode('\\', $module);

        if (count($parts) > 0) {
            $module = $parts[0];
        }

        return $module;
    }

    /**
     * @return mixed
     */
    public function getRoute()
    {
        return app()->request->route()[1]['uses'] ?? 'N/A';
    }

    /**
     * @return mixed
     */
    public function getHttpMethod()
    {
        return $this->request->server('REQUEST_METHOD');
    }

    /**
     * @return mixed
     */
    public function getHttpProtocol()
    {
        return $this->request->server('SERVER_PROTOCOL');
    }

    /**
     * @return mixed
     */
    public function getUserAgent()
    {
        return $this->request->server('HTTP_USER_AGENT');
    }

    /**
     * @return mixed
     */
    public function getHttpVia()
    {
        return $this->request->server('HTTP_VIA');
    }

    /**
     * @return mixed
     */
    public function getHttpForwarded()
    {
        return $this->request->server('HTTP_X_FORWARDED_FOR');
    }

    /**
     * @return bool
     */
    public function isAjax()
    {
        return $this->request->ajax();
    }

    public function getCleanPath()
    {
        $path = $this->request->getPathInfo();
        $quotes = array("\x27", "\x22", "\x60", "\t", "\n", "\r", "*", "%", "<", ">", "?", "!");

        $path = str_replace($quotes, '', $path);
        $path = trim(strip_tags($path), "/ \t\n\r\0\x0B");

        return strtolower($path);
    }

    public function getCurrentTimestamp()
    {
        return time();
    }

    /**
     * @return string
     */
    public function getApiUserId(): string
    {
        if (empty($this->auth)) {

            return User::USER_CLI;
        }

        $userId = $this->auth->getUserId();
        if (empty($userId)) {
            if ($this->isCli()) {

                return User::USER_CLI;
            }

            return User::USER_NO_AUTH;
        }

        return $userId;
    }

    /**
     * @return string
     */
    public function getApiClientId(): string
    {
        if (empty($this->auth)) {

            return User::USER_CLI;
        }

        $clientId = $this->auth->getUserClientId();
        if (empty($clientId)) {
            if ($this->isCli()) {

                return User::USER_CLI;
            }

            return User::USER_NO_AUTH;
        }

        return $clientId;
    }

    /**
     * @return bool
     */
    public function isProductionEnvironment()
    {
        return env('APP_ENV') == self::APP_ENV_PROD;
    }
}
