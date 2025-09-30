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

    // $auth = [
    //     'method' => 'BASIC',
    //     'username' => 'user1',
    //     'password' => '123',
    //     'token' => '',
    // ];
    public function __construct(string $baseUri, bool $tlsVerify = true, int $timeout = 10, array $auth = [])
    {
        $c = [
            'base_uri' => $baseUri,
            'timeout' => $timeout,
            'verify' => $tlsVerify,
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
        ];

        $method = $auth['method'] ?? 'UNKNOWN';

        try {
            if ($method === 'BASIC') {
                $c['auth'] = [$auth['username'], $auth['password']];
            }

            if ($method === 'BEARER') {
                $c['headers']['x-sn-apikey'] = $auth['token'];
            }
        } catch (\Exception $e) {
            throw new RuntimeException(sprintf("Failed to set authentication method %s", $e->getMessage()));
        }

        $this->client = new Client($c);
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
