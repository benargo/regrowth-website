<?php

namespace Tests\Unit\Services\WarcraftLogs;

use App\Services\WarcraftLogs\WarcraftLogsService;
use App\Services\WarcraftLogs\AuthenticationHandler;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ClientAuthenticationTest extends TestCase
{
    /**
     * Tests the retrieval of a token by authentication.
     */
    public function test_token_by_authentication(): void
    {
        $authenticationHandler = new AuthenticationHandler(
            'test_client_id',
            'test_client_secret',
            'https://www.warcraftlogs.com/oauth/token'
        );

        Http::fake([
            'https://www.warcraftlogs.com/oauth/token' => Http::response([
                'access_token' => 'test_access_token',
                'expires_in' => 3600,
            ], 200),
        ]);

        $this->assertEquals('test_access_token', $authenticationHandler->clientToken());
    }

    /**
     * Tests the retrieval of a token from the cache.
     */
    public function test_token_from_cache(): void
    {
        $authenticationHandler = new AuthenticationHandler(
            'test_client_id',
            'test_client_secret',
            'https://www.warcraftlogs.com/oauth/token'
        );

        Cache::expects('get')
            ->with('warcraftlogs.client_token', \Mockery::type('callable'))
            ->andReturn('cached_access_token');

        $this->assertEquals('cached_access_token', $authenticationHandler->clientToken());
    }

    /**
     * Tests that the service returns an instance of AuthenticationHandler.
     */
    public function test_service_returns_instance_of_authentication_handler(): void
    {
        $service = $this->app->make(WarcraftLogsService::class);

        $this->assertInstanceOf(AuthenticationHandler::class, $service->auth());
    }

    /**
     * Tests that the service can return a token.
     */
    public function test_service_can_return_token(): void
    {
        $service = $this->app->make(WarcraftLogsService::class);

        Cache::expects('get')
            ->with('warcraftlogs.client_token', \Mockery::type('callable'))
            ->andReturn('cached_access_token');

        $this->assertEquals('cached_access_token', $service->auth()->clientToken());
    }
}
