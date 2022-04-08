<?php

namespace Levtechdev\SimPaas\Exceptions;

/**
 * Class MysqlCallbackException
 *
 * @package Exceptions
 */
class MysqlCallbackException extends \Exception
{
    /**
     * MysqlCallbackException constructor.
     *
     * @param string          $message
     * @param int             $code
     * @param \Throwable|null $previous
     */
    public function __construct($message = 'MySQL query failure', $code = 500, \Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
