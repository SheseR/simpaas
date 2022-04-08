<?php

namespace Levtechdev\Simpaas\Exceptions;

/**
 * Class MethodNotAllowedException
 *
 * @package Exceptions
 */
class InvalidAppEnvironmentException extends \Exception
{
    public function __construct($message = 'Invalid App Environment', $code = 500, \Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}