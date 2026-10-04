<?php

namespace Tests\Concerns;

use App\Http\Integrations\WarcraftLogs\WarcraftLogsConnector;
use Saloon\Http\Faking\MockResponse;

trait FakesWarcraftLogs
{
    /**
     * Resolve the real container binding so every test exercises the provider wiring.
     */
    protected function makeConnector(): WarcraftLogsConnector
    {
        return $this->app->make(WarcraftLogsConnector::class);
    }

    /**
     * Mock for the OAuth client-credentials token request. Every Saloon::fake() that
     * sends an authenticated request must key this under
     * GetClientCredentialsTokenBasicAuthRequest::class.
     */
    protected function tokenMock(): MockResponse
    {
        return MockResponse::make([
            'access_token' => 'test_token',
            'token_type' => 'Bearer',
            'expires_in' => 3600,
        ]);
    }
}
