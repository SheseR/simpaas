<?php

namespace Levtechdev\Simpaas\Exceptions;

/**
 * Class ExternalServiceNotAvailableException
 *
 * @package Exceptions
 */
class ExternalServiceNotAvailableException extends \Exception
{
    public function __construct($message = 'CDMS service not available', $code = 503, \Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
