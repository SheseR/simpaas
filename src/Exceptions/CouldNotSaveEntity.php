<?php

namespace Levtechdev\Simpaas\Exceptions;

class CouldNotSaveEntity extends \Exception
{
    public function __construct($message = 'Could not save entity', $code = 500, \Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}