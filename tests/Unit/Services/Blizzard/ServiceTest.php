<?php

namespace Tests\Unit\Services\Blizzard;

use App\Services\Blizzard\Client;
use App\Services\Blizzard\Region;
use App\Services\Blizzard\Service;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ConcreteService extends Service
{
    protected string $basePath = '/data/wow';
}

class EmptyBasePathService extends Service
{
    protected string $basePath = '';
}

class ServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.blizzard.client_id' => 'test_client_id',
            'services.blizzard.client_secret' => 'test_client_secret',
            'services.blizzard.region' => 'eu',
            'services.blizzard.locale' => 'en_GB',
            'services.blizzard.namespace' => 'static-classic1x-eu',
        ]);

        Http::fake([
            'eu.battle.net/oauth/token' => Http::response([
                'access_token' => 'test_token',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ]),
        ]);
    }

    public function test_constructor_accepts_client(): void
    {
        $client = new Client('client_id', 'client_secret');
        $service = new ConcreteService($client);

        $this->assertSame($client, $service->getClient());
    }

    public function test_get_makes_request_with_base_path(): void
    {
        Http::fake([
            'eu.battle.net/oauth/token' => Http::response([
                'access_token' => 'test_token',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ]),
            'eu.api.blizzard.com/*' => Http::response(['id' => 19019, 'name' => 'Thunderfury']),
        ]);

        $client = new Client('client_id', 'client_secret');
        $service = new ConcreteService($client);

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('get');
        $response = $method->invoke($service, '/item/19019');

        $this->assertEquals(['id' => 19019, 'name' => 'Thunderfury'], $response->json());

        Http::assertSent(function ($request) {
            if (str_contains($request->url(), 'api.blizzard.com')) {
                return str_contains($request->url(), '/data/wow/item/19019');
            }

            return true;
        });
    }

    public function test_get_includes_query_parameters(): void
    {
        Http::fake([
            'eu.battle.net/oauth/token' => Http::response([
                'access_token' => 'test_token',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ]),
            'eu.api.blizzard.com/*' => Http::response(['items' => []]),
        ]);

        $client = new Client('client_id', 'client_secret');
        $service = new ConcreteService($client);

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('get');
        $method->invoke($service, '/item/index', ['page' => 1, 'limit' => 10]);

        Http::assertSent(function ($request) {
            if (str_contains($request->url(), 'api.blizzard.com')) {
                $query = parse_url($request->url(), PHP_URL_QUERY);
                parse_str($query, $params);

                return isset($params['page']) && $params['page'] === '1'
                    && isset($params['limit']) && $params['limit'] === '10';
            }

            return true;
        });
    }

    public function test_get_json_returns_array(): void
    {
        Http::fake([
            'eu.battle.net/oauth/token' => Http::response([
                'access_token' => 'test_token',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ]),
            'eu.api.blizzard.com/*' => Http::response(['id' => 19019, 'name' => 'Thunderfury']),
        ]);

        $client = new Client('client_id', 'client_secret');
        $service = new ConcreteService($client);

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('getJson');
        $result = $method->invoke($service, '/item/19019');

        $this->assertIsArray($result);
        $this->assertEquals(19019, $result['id']);
        $this->assertEquals('Thunderfury', $result['name']);
    }

    public function test_get_json_throws_on_error_response(): void
    {
        Http::fake([
            'eu.battle.net/oauth/token' => Http::response([
                'access_token' => 'test_token',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ]),
            'eu.api.blizzard.com/*' => Http::response(['error' => 'Not Found'], 404),
        ]);

        $client = new Client('client_id', 'client_secret');
        $service = new ConcreteService($client);

        $this->expectException(\Illuminate\Http\Client\RequestException::class);

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('getJson');
        $method->invoke($service, '/item/99999');
    }

    public function test_build_path_combines_base_path_and_endpoint(): void
    {
        $client = new Client('client_id', 'client_secret');
        $service = new ConcreteService($client);

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('buildPath');

        $this->assertEquals('/data/wow/item/19019', $method->invoke($service, '/item/19019'));
        $this->assertEquals('/data/wow/item/19019', $method->invoke($service, 'item/19019'));
    }

    public function test_build_path_handles_empty_base_path(): void
    {
        $client = new Client('client_id', 'client_secret');
        $service = new EmptyBasePathService($client);

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('buildPath');

        $this->assertEquals('item/19019', $method->invoke($service, '/item/19019'));
        $this->assertEquals('item/19019', $method->invoke($service, 'item/19019'));
    }

    public function test_with_namespace_updates_client_namespace(): void
    {
        $client = new Client('client_id', 'client_secret');
        $service = new ConcreteService($client);

        $result = $service->withNamespace('dynamic-classic1x-eu');

        $this->assertSame($service, $result);
        $this->assertEquals('dynamic-classic1x-eu', $client->getNamespace());
    }

    public function test_with_namespace_is_fluent(): void
    {
        Http::fake([
            'eu.battle.net/oauth/token' => Http::response([
                'access_token' => 'test_token',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ]),
            'eu.api.blizzard.com/*' => Http::response(['realms' => []]),
        ]);

        $client = new Client('client_id', 'client_secret');
        $service = new ConcreteService($client);

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('get');

        $service->withNamespace('dynamic-classic1x-eu');
        $method->invoke($service, '/connected-realm/index');

        Http::assertSent(function ($request) {
            if (str_contains($request->url(), 'api.blizzard.com')) {
                return $request->hasHeader('Battlenet-Namespace', 'dynamic-classic1x-eu');
            }

            return true;
        });
    }

    public function test_get_client_returns_client_instance(): void
    {
        $client = new Client('client_id', 'client_secret');
        $service = new ConcreteService($client);

        $this->assertInstanceOf(Client::class, $service->getClient());
        $this->assertSame($client, $service->getClient());
    }

    public function test_service_uses_client_region_and_locale(): void
    {
        Http::fake([
            'us.battle.net/oauth/token' => Http::response([
                'access_token' => 'us_token',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ]),
            'us.api.blizzard.com/*' => Http::response(['id' => 19019]),
        ]);

        $client = new Client(
            clientId: 'client_id',
            clientSecret: 'client_secret',
            region: Region::US,
            locale: 'en_US',
        );
        $service = new ConcreteService($client);

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('get');
        $method->invoke($service, '/item/19019');

        Http::assertSent(function ($request) {
            if (str_contains($request->url(), 'api.blizzard.com')) {
                return str_contains($request->url(), 'us.api.blizzard.com');
            }

            return true;
        });
    }

    public function test_fresh_sets_ignore_cache_and_is_fluent(): void
    {
        $client = new Client('client_id', 'client_secret');
        $service = new ConcreteService($client);

        $result = $service->fresh();

        $this->assertSame($service, $result);

        $reflection = new \ReflectionClass($service);
        $property = $reflection->getProperty('ignoreCache');
        $this->assertTrue($property->getValue($service));
    }

    public function test_cacheable_caches_result_when_ignore_cache_is_false(): void
    {
        Cache::flush();

        $client = new Client('client_id', 'client_secret');
        $service = new ConcreteService($client);

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('cacheable');

        $callCount = 0;
        $callback = function () use (&$callCount) {
            $callCount++;

            return ['data' => 'test'];
        };

        $result1 = $method->invoke($service, 'test_cache_key', 60, $callback);
        $result2 = $method->invoke($service, 'test_cache_key', 60, $callback);

        $this->assertEquals(['data' => 'test'], $result1);
        $this->assertEquals(['data' => 'test'], $result2);
        $this->assertEquals(1, $callCount);
    }

    public function test_cacheable_bypasses_cache_when_fresh_is_called(): void
    {
        Cache::flush();

        $client = new Client('client_id', 'client_secret');
        $service = new ConcreteService($client);

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('cacheable');

        $callCount = 0;
        $callback = function () use (&$callCount) {
            $callCount++;

            return ['data' => 'fresh'];
        };

        $method->invoke($service, 'fresh_cache_key', 60, $callback);
        $this->assertEquals(1, $callCount);

        $service->fresh();
        $result = $method->invoke($service, 'fresh_cache_key', 60, $callback);

        $this->assertEquals(['data' => 'fresh'], $result);
        $this->assertEquals(2, $callCount);
    }

    public function test_get_resets_ignore_cache_after_request(): void
    {
        Http::fake([
            'eu.battle.net/oauth/token' => Http::response([
                'access_token' => 'test_token',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ]),
            'eu.api.blizzard.com/*' => Http::response(['id' => 19019]),
        ]);

        $client = new Client('client_id', 'client_secret');
        $service = new ConcreteService($client);

        $service->fresh();

        $reflection = new \ReflectionClass($service);
        $property = $reflection->getProperty('ignoreCache');
        $this->assertTrue($property->getValue($service));

        $method = $reflection->getMethod('get');
        $method->invoke($service, '/item/19019');

        $this->assertFalse($property->getValue($service));
    }

    public function test_cacheable_uses_cache_remember_with_ttl(): void
    {
        Cache::flush();
        Cache::shouldReceive('remember')
            ->once()
            ->with('custom_ttl_key', 120, \Mockery::type('callable'))
            ->andReturn(['cached' => true]);

        $client = new Client('client_id', 'client_secret');
        $service = new ConcreteService($client);

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('cacheable');

        $result = $method->invoke($service, 'custom_ttl_key', 120, fn () => ['cached' => true]);

        $this->assertEquals(['cached' => true], $result);
    }

    public function test_cacheable_accepts_null_ttl(): void
    {
        Cache::flush();
        Cache::shouldReceive('remember')
            ->once()
            ->with('null_ttl_key', null, \Mockery::type('callable'))
            ->andReturn(['data' => 'forever']);

        $client = new Client('client_id', 'client_secret');
        $service = new ConcreteService($client);

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('cacheable');

        $result = $method->invoke($service, 'null_ttl_key', null, fn () => ['data' => 'forever']);

        $this->assertEquals(['data' => 'forever'], $result);
    }

    public function test_get_namespace_returns_client_namespace(): void
    {
        $client = new Client('client_id', 'client_secret', namespace: 'test-namespace-eu');
        $service = new ConcreteService($client);

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('getNamespace');

        $this->assertEquals('test-namespace-eu', $method->invoke($service));
    }

    public function test_select_sets_selected_fields_and_is_fluent(): void
    {
        $client = new Client('client_id', 'client_secret');
        $service = new ConcreteService($client);

        $result = $service->select('id', 'name', 'quality');

        $this->assertSame($service, $result);

        $reflection = new \ReflectionClass($service);
        $property = $reflection->getProperty('selectedFields');
        $this->assertEquals(['id', 'name', 'quality'], $property->getValue($service));
    }

    public function test_select_overwrites_previous_selection(): void
    {
        $client = new Client('client_id', 'client_secret');
        $service = new ConcreteService($client);

        $service->select('id', 'name');
        $service->select('quality', 'level');

        $reflection = new \ReflectionClass($service);
        $property = $reflection->getProperty('selectedFields');
        $this->assertEquals(['quality', 'level'], $property->getValue($service));
    }

    public function test_get_json_filters_response_with_selected_fields(): void
    {
        Http::fake([
            'eu.battle.net/oauth/token' => Http::response([
                'access_token' => 'test_token',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ]),
            'eu.api.blizzard.com/*' => Http::response([
                'id' => 19019,
                'name' => 'Thunderfury',
                'quality' => 'Legendary',
                'level' => 60,
                'required_level' => 60,
            ]),
        ]);

        $client = new Client('client_id', 'client_secret');
        $service = new ConcreteService($client);

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('getJson');

        $service->select('id', 'name');
        $result = $method->invoke($service, '/item/19019');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('id', $result);
        $this->assertArrayHasKey('name', $result);
        $this->assertArrayNotHasKey('quality', $result);
        $this->assertArrayNotHasKey('level', $result);
        $this->assertArrayNotHasKey('required_level', $result);
        $this->assertEquals(19019, $result['id']);
        $this->assertEquals('Thunderfury', $result['name']);
    }

    public function test_get_json_resets_selected_fields_after_use(): void
    {
        Http::fake([
            'eu.battle.net/oauth/token' => Http::response([
                'access_token' => 'test_token',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ]),
            'eu.api.blizzard.com/*' => Http::response([
                'id' => 19019,
                'name' => 'Thunderfury',
            ]),
        ]);

        $client = new Client('client_id', 'client_secret');
        $service = new ConcreteService($client);

        $service->select('id', 'name');

        $reflection = new \ReflectionClass($service);
        $property = $reflection->getProperty('selectedFields');
        $this->assertEquals(['id', 'name'], $property->getValue($service));

        $method = $reflection->getMethod('getJson');
        $method->invoke($service, '/item/19019');

        $this->assertEquals([], $property->getValue($service));
    }

    public function test_get_json_returns_full_response_without_select(): void
    {
        Http::fake([
            'eu.battle.net/oauth/token' => Http::response([
                'access_token' => 'test_token',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ]),
            'eu.api.blizzard.com/*' => Http::response([
                'id' => 19019,
                'name' => 'Thunderfury',
                'quality' => 'Legendary',
            ]),
        ]);

        $client = new Client('client_id', 'client_secret');
        $service = new ConcreteService($client);

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('getJson');
        $result = $method->invoke($service, '/item/19019');

        $this->assertArrayHasKey('id', $result);
        $this->assertArrayHasKey('name', $result);
        $this->assertArrayHasKey('quality', $result);
    }

    public function test_select_can_be_chained_with_fresh(): void
    {
        Http::fake([
            'eu.battle.net/oauth/token' => Http::response([
                'access_token' => 'test_token',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ]),
            'eu.api.blizzard.com/*' => Http::response([
                'id' => 19019,
                'name' => 'Thunderfury',
                'quality' => 'Legendary',
            ]),
        ]);

        $client = new Client('client_id', 'client_secret');
        $service = new ConcreteService($client);

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('getJson');

        $result = $service->fresh()->select('id', 'name');
        $this->assertSame($service, $result);

        $response = $method->invoke($service, '/item/19019');

        $this->assertArrayHasKey('id', $response);
        $this->assertArrayHasKey('name', $response);
        $this->assertArrayNotHasKey('quality', $response);
    }

    public function test_get_json_supports_dot_notation_for_nested_fields(): void
    {
        Http::fake([
            'eu.battle.net/oauth/token' => Http::response([
                'access_token' => 'test_token',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ]),
            'eu.api.blizzard.com/*' => Http::response([
                'id' => 19019,
                'name' => 'Thunderfury',
                'media' => [
                    'key' => ['href' => 'https://example.com/key'],
                    'href' => 'https://example.com/media',
                ],
                'quality' => ['type' => 'LEGENDARY', 'name' => 'Legendary'],
            ]),
        ]);

        $client = new Client('client_id', 'client_secret');
        $service = new ConcreteService($client);

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('getJson');

        $service->select('id', 'media.href');
        $result = $method->invoke($service, '/item/19019');

        $this->assertArrayHasKey('id', $result);
        $this->assertArrayHasKey('media', $result);
        $this->assertArrayHasKey('href', $result['media']);
        $this->assertEquals('https://example.com/media', $result['media']['href']);
        $this->assertArrayNotHasKey('key', $result['media']);
        $this->assertArrayNotHasKey('name', $result);
        $this->assertArrayNotHasKey('quality', $result);
    }

    public function test_get_json_supports_deeply_nested_dot_notation(): void
    {
        Http::fake([
            'eu.battle.net/oauth/token' => Http::response([
                'access_token' => 'test_token',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ]),
            'eu.api.blizzard.com/*' => Http::response([
                'id' => 19019,
                'media' => [
                    'key' => [
                        'href' => 'https://example.com/key',
                        'id' => 12345,
                    ],
                ],
            ]),
        ]);

        $client = new Client('client_id', 'client_secret');
        $service = new ConcreteService($client);

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('getJson');

        $service->select('media.key.href');
        $result = $method->invoke($service, '/item/19019');

        $this->assertArrayHasKey('media', $result);
        $this->assertArrayHasKey('key', $result['media']);
        $this->assertArrayHasKey('href', $result['media']['key']);
        $this->assertEquals('https://example.com/key', $result['media']['key']['href']);
        $this->assertArrayNotHasKey('id', $result['media']['key']);
        $this->assertArrayNotHasKey('id', $result);
    }

    public function test_get_json_combines_top_level_and_nested_selections(): void
    {
        Http::fake([
            'eu.battle.net/oauth/token' => Http::response([
                'access_token' => 'test_token',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ]),
            'eu.api.blizzard.com/*' => Http::response([
                'id' => 19019,
                'name' => 'Thunderfury',
                'media' => [
                    'key' => ['href' => 'https://example.com/key'],
                    'href' => 'https://example.com/media',
                ],
                'quality' => 'Legendary',
            ]),
        ]);

        $client = new Client('client_id', 'client_secret');
        $service = new ConcreteService($client);

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('getJson');

        $service->select('id', 'name', 'media.href');
        $result = $method->invoke($service, '/item/19019');

        $this->assertEquals(19019, $result['id']);
        $this->assertEquals('Thunderfury', $result['name']);
        $this->assertEquals('https://example.com/media', $result['media']['href']);
        $this->assertArrayNotHasKey('quality', $result);
        $this->assertArrayNotHasKey('key', $result['media']);
    }

    public function test_get_json_ignores_non_existent_nested_fields(): void
    {
        Http::fake([
            'eu.battle.net/oauth/token' => Http::response([
                'access_token' => 'test_token',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ]),
            'eu.api.blizzard.com/*' => Http::response([
                'id' => 19019,
                'name' => 'Thunderfury',
            ]),
        ]);

        $client = new Client('client_id', 'client_secret');
        $service = new ConcreteService($client);

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('getJson');

        $service->select('id', 'media.href', 'nonexistent.field');
        $result = $method->invoke($service, '/item/19019');

        $this->assertArrayHasKey('id', $result);
        $this->assertEquals(19019, $result['id']);
        $this->assertArrayNotHasKey('media', $result);
        $this->assertArrayNotHasKey('nonexistent', $result);
    }
}
