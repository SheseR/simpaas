<?php

namespace Levtechdev\Simpaas\Middleware\Http;

use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Request;

class MaintenanceModeMiddleware
{
    const POST_ACTIONS_WHITE_LIST = [
        'search'                => true,
    ];

    public function __construct(protected Response $response, protected CorsMiddleware $cors) {}

    /**
     * @param Request  $request
     * @param \Closure $next
     *
     * @return \Illuminate\Http\JsonResponse|mixed
     */
    public function handle(Request $request, \Closure $next)
    {
        if (!is_maintenance() && !is_maintenance_rom()) {

            return $next($request);
        }

        if (is_maintenance_rom()) {
            $method = $request->getRealMethod();
            $action = explode('/', trim($request->getPathInfo(), '/'));
            $action = $action[1] ?? null;
            if ($method == Request::METHOD_GET ||
                ($method == Request::METHOD_POST && isset(self::POST_ACTIONS_WHITE_LIST[$action]))
            ) {

                return $next($request);
            }
        }

        if (str_starts_with($request->getPathInfo(), '/ca') ) {
            $this->response->setStatusCode(Response::HTTP_OK);
            $this->response->header('Retry-After', constant('MAINTENANCE_RETRY_AFTER'));
            $this->response->setContent(
                [
                    'ResponseBody' => null,
                    'Status' =>  'Failed',
                    'PendingUri' => null,
                    'Errors' => [
                        [
                          'ID' => 'SystemUnavailable',
                          'ErrorCode' => '3001',
                          'Message' => 'The API is currently down for maintenance.'
                        ]
                      ]
                ]
            );

            return $this->response->send();
        }

        $this->response->setStatusCode(\Illuminate\Http\Response::HTTP_SERVICE_UNAVAILABLE);
        $this->response->header('Retry-After', constant('MAINTENANCE_RETRY_AFTER'));
        $this->response->setContent(
            [
                'code'    => \Illuminate\Http\Response::HTTP_SERVICE_UNAVAILABLE,
                'message' => is_maintenance() ?
                    'Service Unavailable: Under Maintenance' :
                    'Service Unavailable: Under Read Only mode'
            ]
        );

        // Lumen not has sorting for middlewares, we don`t know which middlewares already executed.
        // Force setting CORS Middleware in current response and stop propagation because here maintenance mode.
        $this->cors->setCorsHeaders($request, $this->response);

        return $this->response->send();
    }
}
