<?php

namespace Levtechdev\SimPaas\Exceptions;

class EntityNotValidException extends \Exception
{
    public function __construct($message = 'Entity Not Valid', $code = 400, \Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}