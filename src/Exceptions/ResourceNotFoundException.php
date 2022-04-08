<?php

namespace Levtechdev\Simpaas\Exceptions;

class ResourceNotFoundException extends \Exception
{
    public function __construct($message = 'Resource Not Found', $code = 404, \Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

}