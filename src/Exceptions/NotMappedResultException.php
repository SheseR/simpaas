<?php

namespace Levtechdev\SimPaas\Exceptions;

/**
 * Class NotMappedResultException
 *
 * @package Exceptions
 */
class NotMappedResultException extends \Exception
{
    /**
     * NotMappedResultException constructor.
     *
     * @param string          $message
     * @param int             $code
     * @param \Throwable|null $previous
     */
    public function __construct($message = 'Catalog product attributes cannot be mapped, see ', $code = 400, \Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}