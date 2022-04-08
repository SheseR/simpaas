<?php

namespace Levtechdev\SimPaas\Exceptions;

class SchemaNotDefinedException extends \Exception
{
    public function __construct($message = 'Entity schema is not defined', $code = 400, \Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}