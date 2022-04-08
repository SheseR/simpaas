<?php

namespace Levtechdev\SimPaas\Exceptions;

class MerchantNegativeCapacityException extends \Exception
{
    public function __construct(
        $message = 'Negative merchant capacity is not allowed',
        $code = 400,
        \Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}
