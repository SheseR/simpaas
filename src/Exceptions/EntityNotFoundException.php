<?php

namespace Levtechdev\SimPaas\Exceptions;

class EntityNotFoundException extends \Exception
{
    public function __construct($message = 'Entity Not Found', $code = 404, \Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}