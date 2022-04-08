<?php

namespace Levtechdev\Simpaas\Exceptions;

/**
 * Class NotImplementedException
 *
 * @package Exceptions
 */
class NotImplementedException extends \Exception
{
    public function __construct($message = 'Not implemented', $code = 501, \Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
