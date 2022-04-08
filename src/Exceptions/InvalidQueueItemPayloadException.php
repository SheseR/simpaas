<?php

namespace Levtechdev\Simpaas\Exceptions;

class InvalidQueueItemPayloadException extends \Exception
{
    public function __construct($message = 'Queue Item Payload is Invalid', $code = 400, \Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}