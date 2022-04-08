<?php

namespace Levtechdev\Simpaas\Exceptions;

class OutsizeExeption extends \Exception
{
    public function __construct($message = 'Outsize', $code = 400, \Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}