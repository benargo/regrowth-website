<?php

namespace App\Services\WarcraftLogs;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class AuthenticationHandler
{
    private const TOKEN_URL = 'https://www.warcraftlogs.com/oauth/token';

    protected string $clientId;

    protected string $clientSecret;

    public function __construct(string $clientId, string $clientSecret)
    {
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
    }

    public function clientToken(): string
    {
        return Cache::get('warcraftlogs.client_token', function () {
            $response = Http::withBasicAuth($this->clientId, $this->clientSecret)->post(self::TOKEN_URL, [
                'grant_type' => 'client_credentials',
            ]);

            if ($response->failed()) {
                throw new \Exception('Failed to retrieve access token from Warcraft Logs API.');
            }

            Cache::put('warcraftlogs.client_token', $response->json()['access_token'], $response->json()['expires_in']);

            return $response->json()['access_token'];
        });
    }
}
