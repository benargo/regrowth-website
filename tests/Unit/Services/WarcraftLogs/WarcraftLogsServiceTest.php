<?php

namespace Tests\Unit\Services\WarcraftLogs;

use App\Exceptions\CacheException;
use App\Services\WarcraftLogs\AuthenticationHandler;
use App\Services\WarcraftLogs\BaseService;
use App\Services\WarcraftLogs\Exceptions\GraphQLException;
use App\Services\WarcraftLogs\Exceptions\RateLimitedException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Concrete implementation for testing the abstract BaseService.
 */
class TestableWarcraftLogsService extends BaseService
{
    public function publicQuery(string $query, array $variables = [], ?int $ttl = null, ?int $timeout = null): array
    {
        return $this->query($query, $variables, $ttl, $timeout);
    }

    public function publicQueryCacheKey(string $query, array $variables): string
    {
        return $this->queryCacheKey($query, $variables);
    }

    public function publicGetGuildId(): int
    {
        return $this->guildId;
    }
}

class WarcraftLogsServiceTest extends TestCase
{
    protected function getService(array $configOverrides = []): TestableWarcraftLogsService
    {
        $config = array_merge([
            'client_id' => 'test_client_id',
            'client_secret' => 'test_client_secret',
            'token_url' => 'https://www.warcraftlogs.com/oauth/token',
            'graphql_url' => 'https://www.warcraftlogs.com/api/v2/client*',
            'guild_id' => 774848,
            'timeout' => 30,
        ], $configOverrides);

        $auth = new AuthenticationHandler($config['client_id'], $config['client_secret']);

        return new TestableWarcraftLogsService($config, $auth);
    }

    protected function fakeGraphqlResponse(array $data, int $status = 200): void
    {
        Http::preventStrayRequests();

        Http::fake([
            'www.warcraftlogs.com/api/v2/client*' => Http::response($data, $status),
        ]);

        // Mock the auth token cache to return our test token
        Cache::shouldReceive('get')
            ->with('warcraftlogs.client_token', \Mockery::type('callable'))
            ->andReturn('test_access_token');
    }

    protected function fakeNotRateLimited(): void
    {
        Cache::shouldReceive('has')
            ->with('warcraftlogs.rate_limited')
            ->andReturn(false);
    }

    public function test_query_sends_graphql_request_with_authorization(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'www.warcraftlogs.com/api/v2/client*' => Http::response([
                'data' => ['guild' => ['id' => 774848]],
            ], 200),
        ]);

        Cache::shouldReceive('get')
            ->with('warcraftlogs.client_token', \Mockery::type('callable'))
            ->andReturn('test_access_token');

        $this->fakeNotRateLimited();

        Cache::shouldReceive('remember')
            ->once()
            ->andReturnUsing(function ($key, $ttl, $callback) {
                return $callback();
            });

        $service = $this->getService();
        $data = $service->publicQuery('query { guild { id } }');

        $this->assertEquals(['guild' => ['id' => 774848]], $data);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'api/v2/client')
                && $request->hasHeader('Authorization', 'Bearer test_access_token')
                && $request['query'] === 'query { guild { id } }';
        });
    }

    public function test_query_includes_variables_when_provided(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'www.warcraftlogs.com/api/v2/client*' => Http::response([
                'data' => ['guild' => ['id' => 774848]],
            ], 200),
        ]);

        Cache::shouldReceive('get')
            ->with('warcraftlogs.client_token', \Mockery::type('callable'))
            ->andReturn('test_access_token');

        $this->fakeNotRateLimited();

        Cache::shouldReceive('remember')
            ->once()
            ->andReturnUsing(function ($key, $ttl, $callback) {
                return $callback();
            });

        $service = $this->getService();
        $service->publicQuery('query($id: Int!) { guild(id: $id) { id } }', ['id' => 774848]);

        Http::assertSent(function ($request) {
            return $request['query'] === 'query($id: Int!) { guild(id: $id) { id } }'
                && $request['variables'] === ['id' => 774848];
        });
    }

    public function test_query_returns_data_portion(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'www.warcraftlogs.com/api/v2/client*' => Http::response([
                'data' => ['guild' => ['id' => 774848, 'name' => 'Test Guild']],
            ], 200),
        ]);

        Cache::shouldReceive('get')
            ->with('warcraftlogs.client_token', \Mockery::type('callable'))
            ->andReturn('test_access_token');

        $this->fakeNotRateLimited();

        Cache::shouldReceive('remember')
            ->once()
            ->andReturnUsing(function ($key, $ttl, $callback) {
                return $callback();
            });

        $service = $this->getService();
        $data = $service->publicQuery('query { guild { id name } }');

        $this->assertEquals(['guild' => ['id' => 774848, 'name' => 'Test Guild']], $data);
    }

    public function test_query_throws_on_graphql_errors(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'www.warcraftlogs.com/api/v2/client*' => Http::response([
                'data' => null,
                'errors' => [
                    ['message' => 'Guild not found', 'path' => ['guild']],
                ],
            ], 200),
        ]);

        Cache::shouldReceive('get')
            ->with('warcraftlogs.client_token', \Mockery::type('callable'))
            ->andReturn('test_access_token');

        $this->fakeNotRateLimited();

        Cache::shouldReceive('remember')
            ->once()
            ->andReturnUsing(function ($key, $ttl, $callback) {
                return $callback();
            });

        $service = $this->getService();

        $this->expectException(GraphQLException::class);
        $this->expectExceptionMessage('GraphQL query failed: Guild not found');

        $service->publicQuery('query { guild { id } }');
    }

    public function test_graphql_exception_contains_all_errors(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'www.warcraftlogs.com/api/v2/client*' => Http::response([
                'data' => null,
                'errors' => [
                    ['message' => 'First error'],
                    ['message' => 'Second error'],
                ],
            ], 200),
        ]);

        Cache::shouldReceive('get')
            ->with('warcraftlogs.client_token', \Mockery::type('callable'))
            ->andReturn('test_access_token');

        $this->fakeNotRateLimited();

        Cache::shouldReceive('remember')
            ->once()
            ->andReturnUsing(function ($key, $ttl, $callback) {
                return $callback();
            });

        $service = $this->getService();

        try {
            $service->publicQuery('query { guild { id } }');
            $this->fail('Expected GraphQLException');
        } catch (GraphQLException $e) {
            $this->assertCount(2, $e->getErrors());
            $this->assertEquals('First error', $e->getFirstError());
        }
    }

    public function test_graphql_exception_has_error_matching_method(): void
    {
        $exception = new GraphQLException([
            ['message' => 'Rate limit exceeded'],
            ['message' => 'Another error'],
        ]);

        $this->assertTrue($exception->hasErrorMatching('/rate limit/i'));
        $this->assertFalse($exception->hasErrorMatching('/not found/i'));
    }

    public function test_query_caches_responses(): void
    {
        Cache::shouldReceive('get')
            ->with('warcraftlogs.client_token', \Mockery::type('callable'))
            ->andReturn('test_access_token');

        $this->fakeNotRateLimited();

        $service = $this->getService();
        $expectedKey = $service->publicQueryCacheKey('query { guild { id } }', []);

        Cache::shouldReceive('remember')
            ->once()
            ->with($expectedKey, 3600, \Mockery::type('callable'))
            ->andReturn(['guild' => ['id' => 774848]]);

        $data = $service->publicQuery('query { guild { id } }');

        $this->assertEquals(['guild' => ['id' => 774848]], $data);
    }

    public function test_fresh_bypasses_cache(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'www.warcraftlogs.com/api/v2/client*' => Http::response([
                'data' => ['guild' => ['id' => 774848]],
            ], 200),
        ]);

        Cache::shouldReceive('get')
            ->with('warcraftlogs.client_token', \Mockery::type('callable'))
            ->andReturn('test_access_token');

        $this->fakeNotRateLimited();

        $service = $this->getService();
        $expectedKey = $service->publicQueryCacheKey('query { guild { id } }', []);

        // fresh(true) should forget the cache key and then remember new value
        Cache::shouldReceive('forget')
            ->once()
            ->with($expectedKey);

        Cache::shouldReceive('remember')
            ->once()
            ->with($expectedKey, 3600, \Mockery::type('callable'))
            ->andReturnUsing(function ($key, $ttl, $callback) {
                return $callback();
            });

        $data = $service->fresh()->publicQuery('query { guild { id } }');

        $this->assertEquals(['guild' => ['id' => 774848]], $data);
    }

    public function test_fresh_false_uses_cache_only(): void
    {
        $this->fakeNotRateLimited();

        $service = $this->getService();
        $expectedKey = $service->publicQueryCacheKey('query { guild { id } }', []);

        Cache::shouldReceive('has')
            ->once()
            ->with($expectedKey)
            ->andReturn(true);

        Cache::shouldReceive('get')
            ->once()
            ->with($expectedKey)
            ->andReturn(['guild' => ['id' => 774848]]);

        $data = $service->fresh(false)->publicQuery('query { guild { id } }');

        $this->assertEquals(['guild' => ['id' => 774848]], $data);
    }

    public function test_fresh_false_throws_when_cache_missing(): void
    {
        $this->fakeNotRateLimited();

        $service = $this->getService();
        $expectedKey = $service->publicQueryCacheKey('query { guild { id } }', []);

        Cache::shouldReceive('has')
            ->once()
            ->with($expectedKey)
            ->andReturn(false);

        $this->expectException(CacheException::class);

        $service->fresh(false)->publicQuery('query { guild { id } }');
    }

    public function test_ignore_cache_skips_cache_entirely(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'www.warcraftlogs.com/api/v2/client*' => Http::response([
                'data' => ['guild' => ['id' => 774848]],
            ], 200),
        ]);

        Cache::shouldReceive('get')
            ->with('warcraftlogs.client_token', \Mockery::type('callable'))
            ->andReturn('test_access_token');

        $this->fakeNotRateLimited();

        // ignoreCache() should not interact with Cache::remember at all
        Cache::shouldNotReceive('remember');
        Cache::shouldNotReceive('forget');

        $service = $this->getService();
        $data = $service->ignoreCache()->publicQuery('query { guild { id } }');

        $this->assertEquals(['guild' => ['id' => 774848]], $data);
    }

    public function test_query_cache_key_is_unique_per_query_and_variables(): void
    {
        $service = $this->getService();

        $key1 = $service->publicQueryCacheKey('query { guild { id } }', []);
        $key2 = $service->publicQueryCacheKey('query { guild { name } }', []);
        $key3 = $service->publicQueryCacheKey('query { guild { id } }', ['id' => 1]);
        $key4 = $service->publicQueryCacheKey('query { guild { id } }', ['id' => 2]);

        $this->assertNotEquals($key1, $key2);
        $this->assertNotEquals($key1, $key3);
        $this->assertNotEquals($key3, $key4);
        $this->assertStringStartsWith('warcraftlogs.', $key1);
    }

    public function test_get_guild_id_returns_configured_value(): void
    {
        $service = $this->getService(['guild_id' => 123456]);

        $this->assertEquals(123456, $service->publicGetGuildId());
    }

    public function test_uses_default_guild_id_when_not_configured(): void
    {
        $auth = new AuthenticationHandler('test_client_id', 'test_client_secret');

        $service = new TestableWarcraftLogsService([
            'client_id' => 'test_client_id',
            'client_secret' => 'test_client_secret',
            'token_url' => 'https://www.warcraftlogs.com/oauth/token',
        ], $auth);

        $this->assertEquals(0, $service->publicGetGuildId());
    }

    public function test_429_response_throws_rate_limited_exception(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'www.warcraftlogs.com/api/v2/client*' => Http::response(
                ['error' => 'Too many requests'],
                429,
            ),
        ]);

        Cache::shouldReceive('get')
            ->with('warcraftlogs.client_token', \Mockery::type('callable'))
            ->andReturn('test_access_token');

        Cache::shouldReceive('has')
            ->with('warcraftlogs.rate_limited')
            ->andReturn(false);

        Cache::shouldReceive('remember')
            ->once()
            ->andReturnUsing(function ($key, $ttl, $callback) {
                return $callback();
            });

        Cache::shouldReceive('put')
            ->once()
            ->with('warcraftlogs.rate_limited', true, 3600);

        Log::shouldReceive('warning')
            ->once()
            ->with('WarcraftLogs API rate limit exceeded. Pausing requests for one hour.');

        $service = $this->getService();

        $this->expectException(RateLimitedException::class);

        $service->publicQuery('query { guild { id } }');
    }

    public function test_subsequent_requests_throw_rate_limited_without_http_call(): void
    {
        Http::preventStrayRequests();

        Cache::shouldReceive('has')
            ->with('warcraftlogs.rate_limited')
            ->andReturn(true);

        $service = $this->getService();

        $this->expectException(RateLimitedException::class);

        $service->publicQuery('query { guild { id } }');

        Http::assertNothingSent();
    }

    public function test_non_429_http_errors_are_rethrown(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'www.warcraftlogs.com/api/v2/client*' => Http::response(
                ['error' => 'Internal Server Error'],
                500,
            ),
        ]);

        Cache::shouldReceive('get')
            ->with('warcraftlogs.client_token', \Mockery::type('callable'))
            ->andReturn('test_access_token');

        Cache::shouldReceive('has')
            ->with('warcraftlogs.rate_limited')
            ->andReturn(false);

        Cache::shouldReceive('remember')
            ->once()
            ->andReturnUsing(function ($key, $ttl, $callback) {
                return $callback();
            });

        $service = $this->getService();

        $this->expectException(RequestException::class);

        $service->publicQuery('query { guild { id } }');
    }

    public function test_custom_ttl_passed_to_query(): void
    {
        Cache::shouldReceive('get')
            ->with('warcraftlogs.client_token', \Mockery::type('callable'))
            ->andReturn('test_access_token');

        $this->fakeNotRateLimited();

        $service = $this->getService();
        $expectedKey = $service->publicQueryCacheKey('query { guild { id } }', []);

        // Custom TTL of 300 seconds should be used
        Cache::shouldReceive('remember')
            ->once()
            ->with($expectedKey, 300, \Mockery::type('callable'))
            ->andReturn(['guild' => ['id' => 774848]]);

        $data = $service->publicQuery('query { guild { id } }', [], 300);

        $this->assertEquals(['guild' => ['id' => 774848]], $data);
    }

    public function test_rate_limit_headers_are_cached_after_successful_query(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'www.warcraftlogs.com/api/v2/client*' => Http::response([
                'data' => ['guild' => ['id' => 774848]],
            ], 200, [
                'x-ratelimit-limit' => '800',
                'x-ratelimit-remaining' => '793',
            ]),
        ]);

        Cache::shouldReceive('get')
            ->with('warcraftlogs.client_token', \Mockery::type('callable'))
            ->andReturn('test_access_token');

        $this->fakeNotRateLimited();

        Cache::shouldReceive('remember')
            ->once()
            ->andReturnUsing(function ($key, $ttl, $callback) {
                return $callback();
            });

        Cache::shouldReceive('put')
            ->once()
            ->with('warcraftlogs.rate_limit', ['limit' => 800, 'remaining' => 793], 3600);

        $service = $this->getService();
        $service->publicQuery('query { guild { id } }');
    }

    public function test_low_rate_limit_remaining_logs_warning(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'www.warcraftlogs.com/api/v2/client*' => Http::response([
                'data' => ['guild' => ['id' => 774848]],
            ], 200, [
                'x-ratelimit-limit' => '800',
                'x-ratelimit-remaining' => '50',
            ]),
        ]);

        Cache::shouldReceive('get')
            ->with('warcraftlogs.client_token', \Mockery::type('callable'))
            ->andReturn('test_access_token');

        $this->fakeNotRateLimited();

        Cache::shouldReceive('remember')
            ->once()
            ->andReturnUsing(function ($key, $ttl, $callback) {
                return $callback();
            });

        Cache::shouldReceive('put')
            ->once()
            ->with('warcraftlogs.rate_limit', ['limit' => 800, 'remaining' => 50], 3600);

        Log::shouldReceive('warning')
            ->once()
            ->with('WarcraftLogs API rate limit tokens running low.', [
                'remaining' => 50,
                'limit' => 800,
            ]);

        $service = $this->getService();
        $service->publicQuery('query { guild { id } }');
    }

    public function test_healthy_rate_limit_remaining_does_not_log_warning(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'www.warcraftlogs.com/api/v2/client*' => Http::response([
                'data' => ['guild' => ['id' => 774848]],
            ], 200, [
                'x-ratelimit-limit' => '800',
                'x-ratelimit-remaining' => '500',
            ]),
        ]);

        Cache::shouldReceive('get')
            ->with('warcraftlogs.client_token', \Mockery::type('callable'))
            ->andReturn('test_access_token');

        $this->fakeNotRateLimited();

        Cache::shouldReceive('remember')
            ->once()
            ->andReturnUsing(function ($key, $ttl, $callback) {
                return $callback();
            });

        Cache::shouldReceive('put')
            ->once()
            ->with('warcraftlogs.rate_limit', ['limit' => 800, 'remaining' => 500], 3600);

        Log::spy();

        $service = $this->getService();
        $service->publicQuery('query { guild { id } }');

        Log::shouldNotHaveReceived('warning');
    }
}
