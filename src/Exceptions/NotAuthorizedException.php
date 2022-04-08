<?php

namespace Levtechdev\SimPaas\Exceptions;

class NotAuthorizedException extends \Exception
{
    public function __construct($message = 'Forbidden', $code = 403, \Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}