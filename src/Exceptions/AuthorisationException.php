<?php

namespace Levtechdev\SimPaas\Exceptions;

class AuthorisationException extends \Exception
{
    public function __construct($message = 'Action access denied for the role', $code = 400, \Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}