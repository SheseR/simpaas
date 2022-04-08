<?php

namespace Levtechdev\Simpaas\Exceptions;

class NotAuthenticatedException extends \Exception
{
    public function __construct($message = 'Not Authenticated', $code = 401, \Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}