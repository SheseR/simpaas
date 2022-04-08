<?php

namespace Levtechdev\Simpaas\Exceptions;

/**
 * Class UnexpectedEntityTypeException
 *
 * @package Exceptions
 */
class UnexpectedEntityTypeException extends \Exception
{
    /**
     * UnexpectedEntityTypeException constructor.
     *
     * @param string          $message
     * @param int             $code
     * @param \Throwable|null $previous
     */
    public function __construct($message = 'Unexpected resource type', $code = 400, \Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}