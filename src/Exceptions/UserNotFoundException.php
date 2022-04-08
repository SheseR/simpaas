<?php

namespace Levtechdev\SimPaas\Exceptions;

class UserNotFoundException extends \Exception
{
    public function __construct($message = 'User Not Found', $code = 404, \Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

}