<?php

namespace Levtechdev\SimPaas\Exceptions;

/**
 * Class FileNotReceivedException
 *
 * @package Exceptions
 */
class FileNotReceivedException extends \Exception
{
    /**
     * FilterNotSpecifiedException constructor.
     *
     * @param string          $message
     * @param int             $code
     * @param \Throwable|null $previous
     */
    public function __construct($message = 'File not received', $code = 400, \Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
