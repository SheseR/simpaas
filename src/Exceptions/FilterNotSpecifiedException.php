<?php

namespace Levtechdev\Simpaas\Exceptions;

class FilterNotSpecifiedException extends \Exception
{
    /**
     * FilterNotSpecifiedException constructor.
     *
     * @param string          $message
     * @param int             $code
     * @param \Throwable|null $previous
     */
    public function __construct($message = 'No filter specified', $code = 400, \Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}