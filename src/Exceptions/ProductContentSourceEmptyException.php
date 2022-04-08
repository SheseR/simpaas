<?php

namespace Levtechdev\Simpaas\Exceptions;

/**
 * Class ProductContentSourceEmptyException
 *
 * @package Exceptions
 */
class ProductContentSourceEmptyException extends \Exception
{
    /**
     * ProductContentSourceEmptyException constructor.
     *
     * @param string          $message
     * @param int             $code
     * @param \Throwable|null $previous
     */
    public function __construct($message = 'Product Content Source is empty', $code = 400, \Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}