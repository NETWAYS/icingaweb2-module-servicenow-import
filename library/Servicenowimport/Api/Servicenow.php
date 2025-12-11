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
        $proxy_type = $auth['proxy_type'] ?? 'UNKNOWN';

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
            $proxy_http_scheme = parse_url($auth['proxy_address'], PHP_URL_SCHEME);
            $proxy_host = parse_url($auth['proxy_address'], PHP_URL_HOST);
            $port = parse_url($auth['proxy_address'], PHP_URL_PORT) ?? null;
            if ($proxy_type === 'HTTP') {
                if ($port === null) {
                    if ($proxy_http_scheme === 'https') {
                        $port = 80;
                    } else {
                        $port = 443;
                    }
                }
                $c['proxy'] = sprintf('%s://%s:%s@%s:%d', $proxy_http_scheme, $auth['proxy_user'], $auth['proxy_password'], $proxy_host, $port);
            } elseif ($proxy_type === 'SOCKS5') {
                $proxy_http_scheme = 'socks5';
                if ($port === null) {
                    $port = 1028;
                }
            }
            $c['proxy'] = sprintf('%s://%s:%s@%s:%d', $proxy_http_scheme, $auth['proxy_user'], $auth['proxy_password'], $proxy_host, $port);
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
