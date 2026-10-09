<?php

namespace App\Services\WarcraftLogs;

use App\Services\WarcraftLogs\Enums\Endpoints;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class AuthenticationHandler
{
    private const CACHE_KEY = 'warcraftlogs:client_token';

    protected string $clientId;

    protected string $clientSecret;

    public function __construct(string $clientId, string $clientSecret)
    {
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
    }

    public function clientToken(): string
    {
        return Cache::get(self::CACHE_KEY, function () {
            $response = Http::withBasicAuth($this->clientId, $this->clientSecret)->post(Endpoints::TOKEN->url(), [
                'grant_type' => 'client_credentials',
            ]);

            if ($response->failed()) {
                throw new \Exception('Failed to retrieve access token from Warcraft Logs API.');
            }

            Cache::put(self::CACHE_KEY, $response->json()['access_token'], $response->json()['expires_in']);

            return $response->json()['access_token'];
        });
    }

    /**
     * Discard the cached client token so the next call to clientToken()
     * fetches a fresh one, e.g. after Warcraft Logs rejects it with a 401.
     */
    public function forgetClientToken(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
