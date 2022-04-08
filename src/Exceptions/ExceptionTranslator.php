<?php

namespace Levtechdev\SimPaas\Exceptions;

interface ExceptionTranslator
{
    public function supports(\Throwable $error): bool;

    public function translateError(\Throwable $error): ErrorResponseInfo;
}
