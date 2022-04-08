<?php

namespace Levtechdev\SimPaas\Exceptions;

/**
 * Class ImageNotReceivedException
 *
 * @package Exceptions
 */
class ImageNotReceivedException extends \Exception
{
    /**
     * FilterNotSpecifiedException constructor.
     *
     * @param string          $message
     * @param int             $code
     * @param \Throwable|null $previous
     */
    public function __construct($message = 'Image not received', $code = 400, \Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}