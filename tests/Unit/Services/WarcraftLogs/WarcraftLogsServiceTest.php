<?php

namespace Tests\Unit\Services\WarcraftLogs;

use App\Services\WarcraftLogs\Exceptions\GraphQLException;
use App\Services\WarcraftLogs\WarcraftLogsService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Concrete implementation for testing the abstract WarcraftLogsService.
 */
class TestableWarcraftLogsService extends WarcraftLogsService
{
    public function publicQuery(string $query, array $variables = [], ?int $timeout = null)
    {
        return $this->query($query, $variables, $timeout);
    }

    public function publicQueryData(string $query, array $variables = [], ?int $timeout = null): array
    {
        return $this->queryData($query, $variables, $timeout);
    }

    public function publicQueryCacheKey(string $query, array $variables): string
    {
        return $this->queryCacheKey($query, $variables);
    }

    public function publicGetCacheTtl(): int
    {
        return $this->getCacheTtl();
    }

    public function publicGetGuildId(): int
    {
        return $this->getGuildId();
    }
}

/**
 * Concrete implementation with custom cache TTL for testing override.
 */
class CustomCacheTtlService extends WarcraftLogsService
{
    protected const DEFAULT_CACHE_TTL = 300;

    public function publicGetCacheTtl(): int
    {
        return $this->getCacheTtl();
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
            'cache_ttl' => 3600,
        ], $configOverrides);

        return new TestableWarcraftLogsService($config);
    }

    protected function fakeGraphqlResponse(array $data, int $status = 200): void
    {
        Http::preventStrayRequests();

        Http::fake([
            'www.warcraftlogs.com/api/v2/client**' => Http::response($data, $status),
        ]);

        // Mock the auth token cache to return our test token
        Cache::shouldReceive('get')
            ->with('warcraftlogs.client_token', \Mockery::type('callable'))
            ->andReturn('test_access_token');
    }

    public function test_query_sends_graphql_request_with_authorization(): void
    {
        $this->fakeGraphqlResponse([
            'data' => ['guild' => ['id' => 774848]],
        ]);

        $service = $this->getService();
        $response = $service->publicQuery('query { guild { id } }');

        $this->assertEquals(200, $response->status());

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'api/v2/client')
                && $request->hasHeader('Authorization', 'Bearer test_access_token')
                && $request['query'] === 'query { guild { id } }';
        });
    }

    public function test_query_includes_variables_when_provided(): void
    {
        $this->fakeGraphqlResponse([
            'data' => ['guild' => ['id' => 774848]],
        ]);

        $service = $this->getService();
        $service->publicQuery('query($id: Int!) { guild(id: $id) { id } }', ['id' => 774848]);

        Http::assertSent(function ($request) {
            return $request['query'] === 'query($id: Int!) { guild(id: $id) { id } }'
                && $request['variables'] === ['id' => 774848];
        });
    }

    public function test_query_data_returns_data_portion(): void
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

        Cache::shouldReceive('remember')
            ->once()
            ->andReturnUsing(function ($key, $ttl, $callback) {
                return $callback();
            });

        $service = $this->getService();
        $data = $service->publicQueryData('query { guild { id name } }');

        $this->assertEquals(['guild' => ['id' => 774848, 'name' => 'Test Guild']], $data);
    }

    public function test_query_data_throws_on_graphql_errors(): void
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

        Cache::shouldReceive('remember')
            ->once()
            ->andReturnUsing(function ($key, $ttl, $callback) {
                return $callback();
            });

        $service = $this->getService();

        $this->expectException(GraphQLException::class);
        $this->expectExceptionMessage('GraphQL query failed: Guild not found');

        $service->publicQueryData('query { guild { id } }');
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

        Cache::shouldReceive('remember')
            ->once()
            ->andReturnUsing(function ($key, $ttl, $callback) {
                return $callback();
            });

        $service = $this->getService();

        try {
            $service->publicQueryData('query { guild { id } }');
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

    public function test_query_data_caches_responses(): void
    {
        Cache::shouldReceive('get')
            ->with('warcraftlogs.client_token', \Mockery::type('callable'))
            ->andReturn('test_access_token');

        $service = $this->getService();
        $expectedKey = $service->publicQueryCacheKey('query { guild { id } }', []);

        Cache::shouldReceive('remember')
            ->once()
            ->with($expectedKey, 3600, \Mockery::type('callable'))
            ->andReturn(['guild' => ['id' => 774848]]);

        $data = $service->publicQueryData('query { guild { id } }');

        $this->assertEquals(['guild' => ['id' => 774848]], $data);
    }

    public function test_fresh_bypasses_cache(): void
    {
        $this->fakeGraphqlResponse([
            'data' => ['guild' => ['id' => 774848]],
        ]);

        $service = $this->getService();
        $data = $service->fresh()->publicQueryData('query { guild { id } }');

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
        $this->assertStringStartsWith('warcraftlogs.service.', $key1);
    }

    public function test_get_cache_ttl_returns_configured_value(): void
    {
        $service = $this->getService(['cache_ttl' => 7200]);

        $this->assertEquals(7200, $service->publicGetCacheTtl());
    }

    public function test_extending_class_can_override_cache_ttl(): void
    {
        $service = new CustomCacheTtlService([
            'client_id' => 'test_client_id',
            'client_secret' => 'test_client_secret',
            'token_url' => 'https://www.warcraftlogs.com/oauth/token',
            'cache_ttl' => 1800,
        ]);

        $this->assertEquals(1800, $service->publicGetCacheTtl());
    }

    public function test_get_guild_id_returns_configured_value(): void
    {
        $service = $this->getService(['guild_id' => 123456]);

        $this->assertEquals(123456, $service->publicGetGuildId());
    }

    public function test_uses_default_values_when_config_options_missing(): void
    {
        $service = new TestableWarcraftLogsService([
            'client_id' => 'test_client_id',
            'client_secret' => 'test_client_secret',
            'token_url' => 'https://www.warcraftlogs.com/oauth/token',
        ]);

        $this->assertEquals(3600, $service->publicGetCacheTtl());
        $this->assertEquals(0, $service->publicGetGuildId());
    }
}
