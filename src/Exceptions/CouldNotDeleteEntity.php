<?php

namespace Levtechdev\Simpaas\Exceptions;

use JetBrains\PhpStorm\Pure;

class CouldNotDeleteEntity extends \Exception
{
    #[Pure] public function __construct($message = 'Could not delete entity', $code = 500, \Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}