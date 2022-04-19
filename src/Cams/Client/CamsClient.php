<?php

namespace Levtechdev\Simpaas\Cams\Client;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\GuzzleException;
use JetBrains\PhpStorm\ArrayShape;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;
use Levtechdev\Simpaas\Exceptions\BadRequestException;
use Levtechdev\Simpaas\Exceptions\EntityNotFoundException;
use Levtechdev\Simpaas\Exceptions\ExternalServiceNotAvailableException;
use Levtechdev\Simpaas\Exceptions\HttpResponseException;

class CamsClient
{
    const HTTP_REQUEST_TIMEOUT = 30;
    const ALLOW_PAYLOAD_METHOD = ['PUT', 'POST', 'UPDATE'];

    /** @var HttpClient */
    protected HttpClient $httpClient;

    /** @var string */
    protected string $baseUrl;

    /** @var string */
    protected string $method = 'GET';

    /** @var string */
    protected string $path;

    /** @var mixed */
    protected mixed $payload;

    /** @var array */
    protected array $query;

    /** @var bool  */
    protected bool $isAvailable;

    /**
     * CamsClient constructor.
     *
     * @param HttpClient $httpClient
     *
     * @throws \Exception
     */
    public function __construct(HttpClient $httpClient)
    {
        $this->httpClient = $httpClient;
        $this->isAvailable = !empty(env('CAMS_AUTH_PASSWORD'));
    }

    /**
     * @return string
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * @param string $path
     *
     * @return $this
     */
    public function setPath(string $path): self
    {
        $this->path = sprintf('/%s/_bulk', $path);

        return $this;
    }

    /**
     * @return string
     */
    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    /**
     * @return $this
     */
    public function setBaseUrl(): self
    {
        $this->baseUrl = env('CAMS_HOST');

        return $this;
    }

    /**
     * @return string
     */
    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * @param string $method
     *
     * @return $this
     */
    public function setMethod(string $method): self
    {
        $this->method = $method;

        return $this;
    }

    /**
     * @return string
     */
    public function getEndpoint(): string
    {
        return sprintf('%s%s', $this->getBaseUrl(), $this->getPath());
    }

    /**
     * @return mixed
     */
    protected function getPayload(): mixed
    {
        return $this->payload;
    }

    /**
     * @param $payload
     *
     * @return $this
     */
    public function setPayload($payload): self
    {
        $this->payload = $payload;

        return $this;
    }

    /**
     * @return array
     */
    #[ArrayShape(['Content-Type' => "string"])]
    protected function getHeaders(): array
    {
        return [
            'Content-Type' => 'application/json',
        ];
    }

    /**
     * @param $index
     * @param $data
     *
     * @return array
     *
     * @throws BadRequestException
     * @throws EntityNotFoundException
     * @throws ExternalServiceNotAvailableException
     * @throws GuzzleException
     * @throws HttpResponseException
     */
    public function bulk($index, $data): array
    {
        return $this->setPayload($data)
            ->setBaseUrl()
            ->setMethod('PUT')
            ->setPath($index)
            ->execute();
    }

    /**
     * @return array
     */
    protected function getAuth(): array
    {
        return [env('CAMS_AUTH_USER'), env('CAMS_AUTH_PASSWORD')];
    }

    /**
     * @return bool
     */
    public function isAvailable(): bool
    {
        return $this->isAvailable;
    }

    /**
     * @return array
     *
     * @throws BadRequestException
     * @throws EntityNotFoundException
     * @throws ExternalServiceNotAvailableException
     * @throws HttpResponseException
     * @throws GuzzleException
     */
    public function execute(): array
    {
        $options = [
            'timeout' => self::HTTP_REQUEST_TIMEOUT,
            'headers' => $this->getHeaders(),
            'auth'    => $this->getAuth(),
        ];

        $method = $this->getMethod();
        if (in_array($method, self::ALLOW_PAYLOAD_METHOD)) {
            $options['body'] = $this->getPayload();
        }

        try {
            $response = $this->httpClient->request($method, $this->getEndpoint(), $options);
        } catch (BadResponseException $e) {
            $response = json_decode($e->getResponse()->getBody()->getContents(), true);
            throw new HttpResponseException(
                503,
                sprintf('CAMS Client - Status Code: %s Error: %s %s - %s',
                    $response['status'] ?? $e->getResponse()->getStatusCode(),
                    $method,
                    $this->getEndpoint(),
                    $response['error']['message'] ?? $e->getMessage()
                )
            );
        }

        if (in_array($response->getStatusCode(),
            [ResponseAlias::HTTP_OK, ResponseAlias::HTTP_CREATED, ResponseAlias::HTTP_ACCEPTED])) {

            return (array)json_decode($response->getBody()->getContents(), true);
        }

        if ($response->getStatusCode() == ResponseAlias::HTTP_SERVICE_UNAVAILABLE) {
            throw new ExternalServiceNotAvailableException('CAMS is not available');
        }

        if ($response->getStatusCode() == ResponseAlias::HTTP_NOT_FOUND) {

            throw new EntityNotFoundException(json_decode((string)$response->getBody(), true));
        }

        throw new BadRequestException('Bad request');
    }
}
