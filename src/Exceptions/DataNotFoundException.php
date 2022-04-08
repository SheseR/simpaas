<?php

namespace Levtechdev\Simpaas\Exceptions;

class DataNotFoundException extends \Exception
{
    public function __construct($message = 'Data Not Found', $code = 404, \Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
