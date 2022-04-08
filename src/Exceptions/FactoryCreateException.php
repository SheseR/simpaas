<?php

namespace Levtechdev\Simpaas\Exceptions;

/**
 * Class FactoryCreateException
 *
 * @package Exceptions
 */
class FactoryCreateException extends \Exception
{
    public function __construct($message = 'Could not create an instance', $code = 500, \Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}