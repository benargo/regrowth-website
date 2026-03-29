<?php

namespace Tests\Unit\Services\Blizzard;

use App\Services\Blizzard\Client;
use App\Services\Blizzard\ItemService;
use App\Services\Blizzard\MediaService;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ItemServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.blizzard.client_id' => 'test_client_id',
            'services.blizzard.client_secret' => 'test_client_secret',
            'services.blizzard.region' => 'eu',
            'services.blizzard.locale' => 'en_GB',
        ]);

        Http::fake([
            'eu.battle.net/oauth/token' => Http::response([
                'access_token' => 'test_token',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ]),
        ]);
    }

    /**
     * Create a mock MediaService.
     */
    protected function createMockMediaService(?callable $configure = null): MediaService&MockInterface
    {
        $mock = Mockery::mock(MediaService::class);

        if ($configure) {
            $configure($mock);
        }

        return $mock;
    }

    /**
     * Create an ItemService with a default no-op MediaService mock.
     */
    protected function makeItemService(Client $client, ?MediaService $mediaService = null): ItemService
    {
        return new ItemService($client, $mediaService ?? $this->createMockMediaService());
    }

    #[Test]
    public function find_returns_item_data(): void
    {
        Http::fake([
            'eu.battle.net/oauth/token' => Http::response([
                'access_token' => 'test_token',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ]),
            'eu.api.blizzard.com/*' => Http::response([
                'id' => 19019,
                'name' => 'Thunderfury, Blessed Blade of the Windseeker',
                'quality' => ['type' => 'LEGENDARY'],
            ]),
        ]);

        $client = new Client('client_id', 'client_secret', namespace: 'static-classicann-eu');
        $service = $this->makeItemService($client);

        $item = $service->find(19019);

        $this->assertIsArray($item);
        $this->assertEquals(19019, $item['id']);
        $this->assertEquals('Thunderfury, Blessed Blade of the Windseeker', $item['name']);
    }

    #[Test]
    public function find_caches_result(): void
    {
        $callCount = 0;

        Http::fake([
            'eu.battle.net/oauth/token' => Http::response([
                'access_token' => 'test_token',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ]),
            'eu.api.blizzard.com/*' => function () use (&$callCount) {
                $callCount++;

                return Http::response(['id' => 19019, 'name' => 'Thunderfury']);
            },
        ]);

        $client = new Client('client_id', 'client_secret', namespace: 'static-classicann-eu');
        $service = $this->makeItemService($client);

        $service->find(19019);
        $service->find(19019);
        $service->find(19019);

        $this->assertEquals(1, $callCount);
    }

    #[Test]
    public function find_uses_namespace_in_cache_key(): void
    {
        Http::fake([
            'eu.battle.net/oauth/token' => Http::response([
                'access_token' => 'test_token',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ]),
            'eu.api.blizzard.com/*' => Http::response(['id' => 19019]),
        ]);

        $client = new Client('client_id', 'client_secret', namespace: 'static-classicann-eu');
        $service = $this->makeItemService($client);

        $service->find(19019);

        $this->assertTrue(Cache::has('blizzard.item.static-classicann-eu.19019'));
    }

    #[Test]
    public function find_throws_exception_for_invalid_item(): void
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
        $service = $this->makeItemService($client);

        $this->expectException(RequestException::class);

        $service->find(99999999);
    }

    #[Test]
    public function fresh_find_bypasses_cache(): void
    {
        $callCount = 0;

        Http::fake([
            'eu.battle.net/oauth/token' => Http::response([
                'access_token' => 'test_token',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ]),
            'eu.api.blizzard.com/*' => function () use (&$callCount) {
                $callCount++;

                return Http::response(['id' => 19019, 'name' => 'Thunderfury']);
            },
        ]);

        $client = new Client('client_id', 'client_secret', namespace: 'static-classicann-eu');
        $service = $this->makeItemService($client);

        $service->find(19019);
        $service->fresh()->find(19019);

        $this->assertEquals(2, $callCount);
    }

    #[Test]
    public function media_returns_icon_data(): void
    {
        $expectedMedia = [
            'assets' => [
                ['key' => 'icon', 'value' => 'https://render.worldofwarcraft.com/item/icon.jpg'],
            ],
        ];

        $mediaService = $this->createMockMediaService(function (MockInterface $mock) use ($expectedMedia) {
            $mock->shouldReceive('find')
                ->once()
                ->with('item', 19019)
                ->andReturn($expectedMedia);
        });

        $client = new Client('client_id', 'client_secret', namespace: 'static-classicann-eu');
        $service = $this->makeItemService($client, $mediaService);

        $media = $service->media(19019);

        $this->assertIsArray($media);
        $this->assertArrayHasKey('assets', $media);
    }

    #[Test]
    public function media_delegates_to_media_service(): void
    {
        $mediaService = $this->createMockMediaService(function (MockInterface $mock) {
            $mock->shouldReceive('find')
                ->once()
                ->with('item', 19019)
                ->andReturn(['assets' => []]);
        });

        $client = new Client('client_id', 'client_secret', namespace: 'static-classicann-eu');
        $service = $this->makeItemService($client, $mediaService);

        $service->media(19019);
    }

    #[Test]
    public function search_returns_results_with_pagination(): void
    {
        Http::fake([
            'eu.battle.net/oauth/token' => Http::response([
                'access_token' => 'test_token',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ]),
            'eu.api.blizzard.com/*' => Http::response([
                'page' => 1,
                'pageSize' => 100,
                'maxPageSize' => 1000,
                'pageCount' => 5,
                'results' => [
                    ['id' => 19019, 'name' => 'Thunderfury'],
                ],
            ]),
        ]);

        $client = new Client('client_id', 'client_secret', namespace: 'static-classicann-eu');
        $service = $this->makeItemService($client);

        $results = $service->search(['name' => 'Thunderfury']);

        $this->assertArrayHasKey('page', $results);
        $this->assertArrayHasKey('pageCount', $results);
        $this->assertArrayHasKey('results', $results);
        $this->assertEquals(1, $results['page']);
        $this->assertEquals(5, $results['pageCount']);
    }

    #[Test]
    public function search_maps_name_parameter(): void
    {
        Http::fake([
            'eu.battle.net/oauth/token' => Http::response([
                'access_token' => 'test_token',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ]),
            'eu.api.blizzard.com/*' => Http::response(['results' => []]),
        ]);

        $client = new Client('client_id', 'client_secret', namespace: 'static-classicann-eu');
        $service = $this->makeItemService($client);

        $service->search(['name' => 'Thunderfury']);

        Http::assertSent(function ($request) {
            if (str_contains($request->url(), 'api.blizzard.com')) {
                $query = parse_url($request->url(), PHP_URL_QUERY);
                parse_str($query, $params);

                return isset($params['name.en_US']) && $params['name.en_US'] === 'Thunderfury';
            }

            return true;
        });
    }

    #[Test]
    public function search_maps_page_parameter(): void
    {
        Http::fake([
            'eu.battle.net/oauth/token' => Http::response([
                'access_token' => 'test_token',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ]),
            'eu.api.blizzard.com/*' => Http::response(['results' => []]),
        ]);

        $client = new Client('client_id', 'client_secret', namespace: 'static-classicann-eu');
        $service = $this->makeItemService($client);

        $service->search(['page' => 3]);

        Http::assertSent(function ($request) {
            if (str_contains($request->url(), 'api.blizzard.com')) {
                $query = parse_url($request->url(), PHP_URL_QUERY);
                parse_str($query, $params);

                return isset($params['_page']) && $params['_page'] === '3';
            }

            return true;
        });
    }

    #[Test]
    public function search_maps_page_size_parameter(): void
    {
        Http::fake([
            'eu.battle.net/oauth/token' => Http::response([
                'access_token' => 'test_token',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ]),
            'eu.api.blizzard.com/*' => Http::response(['results' => []]),
        ]);

        $client = new Client('client_id', 'client_secret', namespace: 'static-classicann-eu');
        $service = $this->makeItemService($client);

        $service->search(['pageSize' => 50]);

        Http::assertSent(function ($request) {
            if (str_contains($request->url(), 'api.blizzard.com')) {
                $query = parse_url($request->url(), PHP_URL_QUERY);
                parse_str($query, $params);

                return isset($params['_pageSize']) && $params['_pageSize'] === '50';
            }

            return true;
        });
    }

    #[Test]
    public function search_caps_page_size_at_maximum(): void
    {
        Http::fake([
            'eu.battle.net/oauth/token' => Http::response([
                'access_token' => 'test_token',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ]),
            'eu.api.blizzard.com/*' => Http::response(['results' => []]),
        ]);

        $client = new Client('client_id', 'client_secret', namespace: 'static-classicann-eu');
        $service = $this->makeItemService($client);

        $service->search(['pageSize' => 5000]);

        Http::assertSent(function ($request) {
            if (str_contains($request->url(), 'api.blizzard.com')) {
                $query = parse_url($request->url(), PHP_URL_QUERY);
                parse_str($query, $params);

                return isset($params['_pageSize']) && $params['_pageSize'] === '1000';
            }

            return true;
        });
    }

    #[Test]
    public function search_accepts_orderby_parameter(): void
    {
        Http::fake([
            'eu.battle.net/oauth/token' => Http::response([
                'access_token' => 'test_token',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ]),
            'eu.api.blizzard.com/*' => Http::response(['results' => []]),
        ]);

        $client = new Client('client_id', 'client_secret', namespace: 'static-classicann-eu');
        $service = $this->makeItemService($client);

        $service->search(['orderby' => 'name']);

        Http::assertSent(function ($request) {
            if (str_contains($request->url(), 'api.blizzard.com')) {
                $query = parse_url($request->url(), PHP_URL_QUERY);
                parse_str($query, $params);

                return isset($params['orderby']) && $params['orderby'] === 'name';
            }

            return true;
        });
    }

    #[Test]
    public function search_caches_results(): void
    {
        $callCount = 0;

        Http::fake([
            'eu.battle.net/oauth/token' => Http::response([
                'access_token' => 'test_token',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ]),
            'eu.api.blizzard.com/*' => function () use (&$callCount) {
                $callCount++;

                return Http::response(['results' => []]);
            },
        ]);

        $client = new Client('client_id', 'client_secret', namespace: 'static-classicann-eu');
        $service = $this->makeItemService($client);

        $service->search(['name' => 'Thunderfury']);
        $service->search(['name' => 'Thunderfury']);

        $this->assertEquals(1, $callCount);
    }

    #[Test]
    public function search_cache_key_varies_by_parameters(): void
    {
        $callCount = 0;

        Http::fake([
            'eu.battle.net/oauth/token' => Http::response([
                'access_token' => 'test_token',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ]),
            'eu.api.blizzard.com/*' => function () use (&$callCount) {
                $callCount++;

                return Http::response(['results' => []]);
            },
        ]);

        $client = new Client('client_id', 'client_secret', namespace: 'static-classicann-eu');
        $service = $this->makeItemService($client);

        $service->search(['name' => 'Thunderfury']);
        $service->search(['name' => 'Ashkandi']);

        $this->assertEquals(2, $callCount);
    }

    #[Test]
    public function with_namespace_affects_cache_keys(): void
    {
        $callCount = 0;

        Http::fake([
            'eu.battle.net/oauth/token' => Http::response([
                'access_token' => 'test_token',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ]),
            'eu.api.blizzard.com/*' => function () use (&$callCount) {
                $callCount++;

                return Http::response(['id' => 19019]);
            },
        ]);

        $client = new Client('client_id', 'client_secret', namespace: 'static-classicann-eu');
        $service = $this->makeItemService($client);

        $service->find(19019);
        $service->withNamespace('static-classic1x-eu')->find(19019);

        $this->assertEquals(2, $callCount);
        $this->assertTrue(Cache::has('blizzard.item.static-classic1x-eu.19019'));
    }
}
