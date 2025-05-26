<?php

namespace Icinga\Module\Servicenowimport\Api;

use GuzzleHttp\Client;
use RuntimeException;

/*
 * ServiceNow API Client
 */
class Servicenow
{
    protected $client = null;

    public function __construct(string $baseUri, string $username, string $password, bool $tlsVerify = true, int $timeout = 10)
    {
        $this->client = new Client(
            [
                'base_uri' => $baseUri,
                'auth' => [$username, $password],
                'timeout' => $timeout,
                'verify' => $tlsVerify,
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ],
            ]
        );
    }

    /**
     * @param  string $endpoint
     * @param  array  $params
     * @param  string $method
     * @return mixed
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function request(string $endpoint, array $params = [], string $method = "GET")
    {
        try {
            $response = $this->client->request($method, $endpoint, $params);
            return $response->getBody()->getContents();
        } catch (\Exception $e) {
            throw new RuntimeException(sprintf("Request failed: %s", $e->getMessage()));
        }
    }
}
