<?php

namespace Levtechdev\Simpaas\Exceptions;

/**
 * Class EventLoggerSendMsgException
 *
 * @package Exceptions
 */
class EventLoggerSendMsgException extends \Exception
{
    public function __construct($message = 'Events monitoring service is not available', $code = 500, \Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}