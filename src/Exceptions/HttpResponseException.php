<?php

namespace Levtechdev\Simpaas\Exceptions;

class HttpResponseException extends \Exception
{
    public function __construct($code = 500, $message = 'Server returned not successful response: %s', \Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
