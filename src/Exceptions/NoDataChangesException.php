<?php

namespace Levtechdev\Simpaas\Exceptions;

class NoDataChangesException extends \Exception
{
    public function __construct($message = 'Cannot update entities, no data changes have been detected', $code = 400, \Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
