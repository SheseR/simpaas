<?php

namespace Levtechdev\Simpaas\Exceptions;

class StorageSourceMissingException extends \Exception
{
    public function __construct($entityType, $message = 'Storage source is missing for %s', $code = 500, \Throwable $previous = null)
    {
        parent::__construct(sprintf($message, $entityType), $code, $previous);
    }
}