<?php

namespace Levtechdev\SimPaas\Exceptions;

/**
 * Class MethodNotAllowedException
 *
 * @package Exceptions
 */
class MethodNotAllowedException extends \Exception
{
    public function __construct($message = 'Method not allowed', $code = 500, \Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}