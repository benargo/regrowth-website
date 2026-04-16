<?php

namespace Tests\Unit\Services\WarcraftLogs;

use App\Services\WarcraftLogs\AuthenticationHandler;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ClientAuthenticationTest extends TestCase
{
    private const TOKEN_URL = 'https://fresh.warcraftlogs.com/oauth/token';

    protected function setUp(): void
    {
        parent::setUp();

        // Ensure warcraftlogs config has all required keys for service provider
        config([
            'services.warcraftlogs' => array_merge(
                config('services.warcraftlogs', []),
                [
                    'client_id' => 'test_client_id',
                    'client_secret' => 'test_client_secret',
                ]
            ),
        ]);
    }

    /**
     * Tests the retrieval of a token by authentication.
     */
    #[Test]
    public function token_by_authentication(): void
    {
        $authenticationHandler = new AuthenticationHandler(
            'test_client_id',
            'test_client_secret'
        );

        Http::fake([
            self::TOKEN_URL => Http::response([
                'access_token' => 'test_access_token',
                'expires_in' => 3600,
            ], 200),
        ]);

        $this->assertEquals('test_access_token', $authenticationHandler->clientToken());
    }

    /**
     * Tests the retrieval of a token from the cache.
     */
    #[Test]
    public function token_from_cache(): void
    {
        $authenticationHandler = new AuthenticationHandler(
            'test_client_id',
            'test_client_secret'
        );

        Cache::expects('get')
            ->with('warcraftlogs:client_token', \Mockery::type('callable'))
            ->andReturn('cached_access_token');

        $this->assertEquals('cached_access_token', $authenticationHandler->clientToken());
    }

    /**
     * Tests that the AuthenticationHandler can be resolved from the container.
     */
    #[Test]
    public function authentication_handler_can_be_resolved(): void
    {
        $authHandler = $this->app->make(AuthenticationHandler::class);

        $this->assertInstanceOf(AuthenticationHandler::class, $authHandler);
    }

    /**
     * Tests that the container-resolved AuthenticationHandler can return a token.
     */
    #[Test]
    public function resolved_authentication_handler_can_return_token(): void
    {
        Cache::expects('get')
            ->with('warcraftlogs:client_token', \Mockery::type('callable'))
            ->andReturn('cached_access_token');

        $authHandler = $this->app->make(AuthenticationHandler::class);

        $this->assertEquals('cached_access_token', $authHandler->clientToken());
    }
}
