<?php

namespace Levtechdev\Simpaas\Exceptions;

use ArrayAccess;
use JsonSerializable;
use Throwable;

class ErrorResponseInfo
{
    /** @var array|object  */
    private array|object $serializableBody;
    private ?int $responseCode;
    private array $additionalHeaders = [];
    private Throwable $throwable;
    private ?string $message;

    public function __construct(Throwable $error, string $message = null)
    {
        $this->throwable = $error;
        $this->message = $message;
    }

    public function hasResponseCode(): bool
    {
        return isset($this->responseCode);
    }

    /**
     * @return array|object
     */
    public function getSerializableBody(): array|object
    {
        return $this->serializableBody;
    }

    /**
     * @return array
     */
    public function getAdditionalHeaders(): array
    {
        return $this->additionalHeaders;
    }

    /**
     * @return int|null
     */
    public function getResponseCode(): ?int
    {
        return $this->responseCode;
    }

    /**
     * @param array|object $serializableBody
     * @return $this
     */
    public function setSerializableBody(array|object $serializableBody): ErrorResponseInfo
    {
        $this->serializableBody = $serializableBody;

        return $this;
    }

    /**
     * @param int|null $responseCode
     * @return ErrorResponseInfo
     */
    public function setResponseCode(?int $responseCode): ErrorResponseInfo
    {
        $this->responseCode = $responseCode;

        return $this;
    }

    /**
     * @param array $additionalHeaders
     * @return ErrorResponseInfo
     */
    public function setAdditionalHeaders(array $additionalHeaders): ErrorResponseInfo
    {
        $this->additionalHeaders = $additionalHeaders;

        return $this;
    }

    /**
     * @return Throwable
     */
    public function getThrowable(): Throwable
    {
        return $this->throwable;
    }

    /**
     * @return string|null
     */
    public function getMessage(): ?string
    {
        return $this->message;
    }
}
