<?php

namespace Icinga\Module\Servicenowimport\Api;

use Icinga\Util\Json;

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

            if ($method === 'OAUTH') {
                // Note: We currently don't store the token between requests
                $accessToken = $this->fetchAccessToken($baseUri, $auth);
                $c['headers']['Authorization'] = 'Bearer ' . $accessToken;
            }
        } catch (\Exception $e) {
            throw new RuntimeException(sprintf("Failed to set authentication method %s", $e->getMessage()));
        }

        $proxy = $this->buildProxyUrl($auth);

        if ($proxy !== null) {
            $c['proxy'] = $proxy;
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

    /**
     * buildProxyUrl returns a proxy URL or null if no proxy is configured.
     */
    private function buildProxyUrl(array $auth): ?string
    {
        $proxyType = $auth['proxy_type'] ?? '';

        if ($proxyType !== 'HTTP' && $proxyType !== 'SOCKS5') {
            return null;
        }

        try {
            $rawProxy = (string) ($auth['proxy_address'] ?? '');
            // Support input with and without schema prefix
            $proxyAddress = parse_url(strpos($rawProxy, '://') === false ? 'https://' . $rawProxy : $rawProxy);

            if (! is_array($proxyAddress) || empty($proxyAddress['host'])) {
                throw new RuntimeException(sprintf('invalid proxy address "%s"', $rawProxy));
            }

            $proxyHost = $proxyAddress['host'];

            if ($proxyType === 'HTTP') {
                $proxyScheme = $proxyAddress['scheme'] ?? 'https';
                $defaultPort = $proxyScheme === 'http' ? 80 : 443;
                $proxyPort = $proxyAddress['port'] ?? $defaultPort;
            } else {
                // socks5h: let the proxy resolve DNS names
                $proxyScheme = 'socks5h';
                $proxyPort = $proxyAddress['port'] ?? 1080;
            }

            // Only add credentials if a user is set, and URL-encode them so special characters wont break the proxy URL
            $userinfo = '';
            if (isset($auth['proxy_user']) && $auth['proxy_user'] !== '') {
                $userinfo = rawurlencode((string) $auth['proxy_user'])
                    . ':' . rawurlencode((string) ($auth['proxy_password'] ?? ''))
                    . '@';
            }

            return sprintf('%s://%s%s:%d', $proxyScheme, $userinfo, $proxyHost, $proxyPort);
        } catch (\Exception $e) {
            throw new RuntimeException(sprintf("Failed to set proxy method %s", $e->getMessage()));
        }
    }

    /**
     * fetchAccessToken gets a new access token from the authorization server.
     */
    private function fetchAccessToken(string $baseUri, array $oauthConfig): string
    {
        $c = new Client(['base_uri' => $baseUri]);

        $params = [
            'grant_type' => 'client_credentials',
            'client_id' => $oauthConfig['client_id'],
            'client_secret' => $oauthConfig['client_secret'],
        ];

        if (array_key_exists('scope', $oauthConfig)) {
            $params['scope'] = $oauthConfig['scope'];
        }

        try {
            $response = $c->post('/oauth_token.do', ['form_params' => $params]);
            $data = Json::decode($response->getBody(), true);
        } catch (\Exception $e) {
            throw new RuntimeException(sprintf("Request failed: %s", $e->getMessage()));
        }

        if (array_key_exists('access_token', $data)) {
            return $data['access_token'];
        }

        throw new RuntimeException(sprintf("No access token in response", $data));
    }
}
