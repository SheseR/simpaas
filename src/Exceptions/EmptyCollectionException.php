<?php

namespace Levtechdev\Simpaas\Exceptions;

class EmptyCollectionException extends \Exception
{
    public function __construct($message = 'Data Collection is empty - no items corresponding to filter conditions', $code = 404, \Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}