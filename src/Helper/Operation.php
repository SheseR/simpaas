<?php

namespace Levtechdev\SimPaas\Helper;

/**
 * Class Operation
 *
 */
class Operation
{
    /** @var string|null  */
    protected ?string $operationId = null;

    /**
     * @param string $operationId
     */
    public function setOperationId(string $operationId)
    {
        $this->operationId = $operationId;
    }

    /**
     * @return string
     */
    public function getOperationId(): string
    {
        if ($this->operationId === null) {
            $this->setOperationId((string) getmypid());
        }

        return $this->operationId;
    }
}