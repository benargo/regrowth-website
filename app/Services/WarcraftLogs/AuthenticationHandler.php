<?php

namespace App\Services\WarcraftLogs;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class AuthenticationHandler
{
    protected string $clientId;
    protected string $clientSecret;
    protected string $tokenUrl;

    public function __construct(string $clientId, string $clientSecret, string $tokenUrl)
    {
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->tokenUrl = $tokenUrl;
    }

    public function clientToken(): string
    {
        return Cache::get('warcraftlogs.client_token', function () {
            $response = Http::withBasicAuth($this->clientId, $this->clientSecret)->post($this->tokenUrl, [
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