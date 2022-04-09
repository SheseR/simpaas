<?php

namespace Levtechdev\Simpaas\Middleware\Http;

use Levtechdev\Simpaas\Helper\Operation;

class OperationMiddleware
{
    /**
     * OperationMiddleware constructor.
     * @param Operation $operationHelper
     */
    public function __construct(protected Operation $operationHelper)
    {
    }

    /**
     * @param $request
     * @param \Closure $next
     * @return mixed
     */
    public function handle($request, \Closure $next): mixed
    {
        if (empty($this->operationHelper->getOperationId())) {
            $operationId = uniqid();
            $this->operationHelper->setOperationId($operationId);
            if (extension_loaded('newrelic')) {
                newrelic_add_custom_parameter('operation_id', $operationId);
            }
        }

        return $next($request);
    }
}