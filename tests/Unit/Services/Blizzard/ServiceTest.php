<?php

namespace Tests\Unit\Services\Blizzard;

use App\Services\Blizzard\Client;
use App\Services\Blizzard\Region;
use App\Services\Blizzard\Service;
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
}
