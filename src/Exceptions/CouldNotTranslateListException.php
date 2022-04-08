<?php

namespace Levtechdev\Simpaas\Exceptions;

/**
 * Class CouldNotTranslateListException
 *
 * @package Exceptions
 */
class CouldNotTranslateListException extends \Exception
{
    public function __construct($message = 'Could not translate list', $code = 400, \Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
