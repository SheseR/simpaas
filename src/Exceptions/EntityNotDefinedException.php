<?php

namespace Levtechdev\Simpaas\Exceptions;

class EntityNotDefinedException extends \Exception
{
    public function __construct($message = 'Entity is not defined', $code = 500, \Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}