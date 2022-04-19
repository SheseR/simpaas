<?php
namespace Levtechdev\Simpaas\Cams\Client;

use GuzzleHttp\Exception\GuzzleException;
use Levtechdev\Simpaas\Exceptions\BadRequestException;
use Levtechdev\Simpaas\Exceptions\EntityNotFoundException;
use Levtechdev\Simpaas\Exceptions\ExternalServiceNotAvailableException;
use Levtechdev\Simpaas\Exceptions\HttpResponseException;

class CamsClientAdapter
{
    public function __construct(protected CamsClient $client)
    {
    }

    /**
     * @return CamsClient
     */
    protected function getClient(): CamsClient
    {
        return $this->client;
    }

    /**
     * @param string $destination
     * @param array $records
     *
     * @return array
     *
     * @throws GuzzleException
     * @throws BadRequestException
     * @throws EntityNotFoundException
     * @throws ExternalServiceNotAvailableException
     * @throws HttpResponseException
     */
    public function addRecords(string $destination, array $records): array
    {
        if (!$this->getClient()->isAvailable()) {

            return [];
        }

        if (empty($records)) {

            throw new \InvalidArgumentException('Specified empty data when calling ' . __METHOD__ . '()');
        }

        $data = [];
        foreach ($records as $key => $value) {
            $data[] = json_encode([
                'create' => new \stdClass(),
            ]);
            $data[] = json_encode($value);
        }

        $payload = join("\n", $data) . "\n";

        return $this->getClient()->bulk($destination, $payload);
    }
}
