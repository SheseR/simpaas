<?php

namespace Levtechdev\Simpaas\Exceptions;

use Throwable;

/**
 * Class UnexpectedValueException
 *
 * @package Exceptions
 */
class UnexpectedValueException extends \UnexpectedValueException
{
    /**
     * UnexpectedValueException constructor.
     *
     * @param string         $message
     * @param int            $code
     * @param Throwable|null $previous
     */
    public function __construct($message = "Unexpected value in a method", $code = 404, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
