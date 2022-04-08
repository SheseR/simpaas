<?php

namespace Levtechdev\SimPaas\Exceptions;

/**
 * Class MysqlUpsertException
 *
 * @package Exceptions
 */
class MysqlUpsertException extends \Exception
{
    /**
     * MysqlUpsertException constructor.
     *
     * @param string          $message
     * @param int             $code
     * @param \Throwable|null $previous
     */
    public function __construct($message = 'Could not upsert entities collection', $code = 500, \Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
