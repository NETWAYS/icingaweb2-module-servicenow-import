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
        $proxyType = $auth['proxy_type'] ?? 'UNKNOWN';

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

        try {
            $proxyAddress = parse_url($auth['proxy_address']);
            $proxyHost = $proxyAddress['host'];
            $proxyScheme = 'https';
            $proxyPort = 443;

            if ($proxyType === 'HTTP') {
                $proxyScheme = $proxyAddress['scheme'] ?? 'https';
                $defaultPort = $proxyScheme === 'http' ? 80 : 443;
                $proxyPort = $proxyAddress['port'] ?? $defaultPort;
            } elseif ($proxyType === 'SOCKS5') {
                $proxyScheme = 'socks5';
                $proxyPort = $proxyAddress['port'] ?? 1028;
            }

            $c['proxy'] = sprintf('%s://%s:%s@%s:%d', $proxyScheme, $auth['proxy_user'], $auth['proxy_password'], $proxyHost, $proxyPort);
        } catch (\Exception $e) {
            throw new RuntimeException(sprintf("Failed to set proxy method %s", $e->getMessage()));
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
