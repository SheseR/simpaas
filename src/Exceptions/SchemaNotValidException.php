<?php

namespace Levtechdev\SimPaas\Exceptions;

class SchemaNotValidException extends \Exception
{
    public function __construct($message = 'Data definition is not valid', $code = 400, \Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}