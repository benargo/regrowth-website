<?php

namespace Tests\Unit\Services\Blizzard;

use App\Services\Blizzard\Client;
use App\Services\Blizzard\MediaService;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Tests\TestCase;

class MediaServiceTest extends TestCase
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

    public function test_constructor_sets_static_namespace(): void
    {
        $client = new Client('client_id', 'client_secret');
        new MediaService($client);

        $this->assertEquals('static-eu', $client->getNamespace());
    }

    public function test_find_returns_media_data(): void
    {
        Http::fake([
            'eu.battle.net/oauth/token' => Http::response([
                'access_token' => 'test_token',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ]),
            'eu.api.blizzard.com/*' => Http::response([
                'assets' => [
                    ['key' => 'icon', 'value' => 'https://render.worldofwarcraft.com/icons/56/inv_sword.jpg'],
                ],
            ]),
        ]);

        $client = new Client('client_id', 'client_secret');
        $service = new MediaService($client);

        $media = $service->find('item', 19019);

        $this->assertIsArray($media);
        $this->assertArrayHasKey('assets', $media);
        $this->assertEquals('icon', $media['assets'][0]['key']);
    }

    public function test_find_caches_result(): void
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

                return Http::response(['assets' => []]);
            },
        ]);

        $client = new Client('client_id', 'client_secret');
        $service = new MediaService($client);

        $service->find('item', 19019);
        $service->find('item', 19019);
        $service->find('item', 19019);

        $this->assertEquals(1, $callCount);
    }

    public function test_find_uses_namespace_in_cache_key(): void
    {
        Http::fake([
            'eu.battle.net/oauth/token' => Http::response([
                'access_token' => 'test_token',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ]),
            'eu.api.blizzard.com/*' => Http::response(['assets' => []]),
        ]);

        $client = new Client('client_id', 'client_secret');
        $service = new MediaService($client);

        $service->find('item', 19019);

        $this->assertTrue(Cache::has('blizzard.media.item.static-eu.19019'));
    }

    public function test_find_throws_exception_for_invalid_media(): void
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
        $service = new MediaService($client);

        $this->expectException(RequestException::class);

        $service->find('item', 99999999);
    }

    public function test_find_throws_exception_for_invalid_tag(): void
    {
        $client = new Client('client_id', 'client_secret');
        $service = new MediaService($client);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid tag(s): invalid. Allowed tags are: item, spell, playable-class');

        $service->find('invalid', 19019);
    }

    public function test_find_accepts_item_tag(): void
    {
        Http::fake([
            'eu.battle.net/oauth/token' => Http::response([
                'access_token' => 'test_token',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ]),
            'eu.api.blizzard.com/*' => Http::response(['assets' => []]),
        ]);

        $client = new Client('client_id', 'client_secret');
        $service = new MediaService($client);

        $media = $service->find('item', 19019);

        $this->assertIsArray($media);

        Http::assertSent(function ($request) {
            if (str_contains($request->url(), 'api.blizzard.com')) {
                return str_contains($request->url(), '/media/item/19019');
            }

            return true;
        });
    }

    public function test_find_accepts_spell_tag(): void
    {
        Http::fake([
            'eu.battle.net/oauth/token' => Http::response([
                'access_token' => 'test_token',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ]),
            'eu.api.blizzard.com/*' => Http::response(['assets' => []]),
        ]);

        $client = new Client('client_id', 'client_secret');
        $service = new MediaService($client);

        $media = $service->find('spell', 12345);

        $this->assertIsArray($media);

        Http::assertSent(function ($request) {
            if (str_contains($request->url(), 'api.blizzard.com')) {
                return str_contains($request->url(), '/media/spell/12345');
            }

            return true;
        });
    }

    public function test_find_accepts_playable_class_tag(): void
    {
        Http::fake([
            'eu.battle.net/oauth/token' => Http::response([
                'access_token' => 'test_token',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ]),
            'eu.api.blizzard.com/*' => Http::response(['assets' => []]),
        ]);

        $client = new Client('client_id', 'client_secret');
        $service = new MediaService($client);

        $media = $service->find('playable-class', 1);

        $this->assertIsArray($media);

        Http::assertSent(function ($request) {
            if (str_contains($request->url(), 'api.blizzard.com')) {
                return str_contains($request->url(), '/media/playable-class/1');
            }

            return true;
        });
    }

    public function test_find_fresh_bypasses_cache(): void
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

                return Http::response(['assets' => []]);
            },
        ]);

        $client = new Client('client_id', 'client_secret');
        $service = new MediaService($client);

        $service->find('item', 19019);
        $service->findFresh('item', 19019);

        $this->assertEquals(2, $callCount);
    }

    public function test_find_fresh_clears_existing_cache(): void
    {
        Http::fake([
            'eu.battle.net/oauth/token' => Http::response([
                'access_token' => 'test_token',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ]),
            'eu.api.blizzard.com/*' => Http::response(['assets' => []]),
        ]);

        $client = new Client('client_id', 'client_secret');
        $service = new MediaService($client);

        $service->find('item', 19019);
        $this->assertTrue(Cache::has('blizzard.media.item.static-eu.19019'));

        Cache::shouldReceive('forget')
            ->once()
            ->with('blizzard.media.item.static-eu.19019');

        Cache::shouldReceive('get')
            ->with('blizzard_access_token_eu')
            ->andReturn('test_token');

        Http::fake([
            'eu.battle.net/oauth/token' => Http::response([
                'access_token' => 'test_token',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ]),
            'eu.api.blizzard.com/*' => Http::response(['assets' => []]),
        ]);

        $service->findFresh('item', 19019);
    }

    public function test_find_fresh_throws_exception_for_invalid_tag(): void
    {
        $client = new Client('client_id', 'client_secret');
        $service = new MediaService($client);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid tag(s): invalid. Allowed tags are: item, spell, playable-class');

        $service->findFresh('invalid', 19019);
    }

    public function test_search_requires_tags_parameter(): void
    {
        $client = new Client('client_id', 'client_secret');
        $service = new MediaService($client);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The "tags" parameter is required for media search.');

        $service->search(['name' => 'test']);
    }

    public function test_search_returns_results(): void
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
                    ['id' => 19019],
                ],
            ]),
        ]);

        $client = new Client('client_id', 'client_secret');
        $service = new MediaService($client);

        $results = $service->search(['tags' => ['item']]);

        $this->assertArrayHasKey('page', $results);
        $this->assertArrayHasKey('pageCount', $results);
        $this->assertArrayHasKey('results', $results);
        $this->assertEquals(1, $results['page']);
    }

    public function test_search_throws_for_invalid_tags(): void
    {
        $client = new Client('client_id', 'client_secret');
        $service = new MediaService($client);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid tag(s): invalid. Allowed tags are: item, spell, playable-class');

        $service->search(['tags' => ['item', 'invalid']]);
    }

    public function test_search_maps_tags_parameter(): void
    {
        Http::fake([
            'eu.battle.net/oauth/token' => Http::response([
                'access_token' => 'test_token',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ]),
            'eu.api.blizzard.com/*' => Http::response(['results' => []]),
        ]);

        $client = new Client('client_id', 'client_secret');
        $service = new MediaService($client);

        $service->search(['tags' => ['item', 'spell']]);

        Http::assertSent(function ($request) {
            if (str_contains($request->url(), 'api.blizzard.com')) {
                $query = parse_url($request->url(), PHP_URL_QUERY);
                parse_str($query, $params);

                return isset($params['_tags']) && $params['_tags'] === 'item,spell';
            }

            return true;
        });
    }

    public function test_search_maps_item_id_parameter(): void
    {
        Http::fake([
            'eu.battle.net/oauth/token' => Http::response([
                'access_token' => 'test_token',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ]),
            'eu.api.blizzard.com/*' => Http::response(['results' => []]),
        ]);

        $client = new Client('client_id', 'client_secret');
        $service = new MediaService($client);

        $service->search(['tags' => ['item'], 'itemId' => 19019]);

        Http::assertSent(function ($request) {
            if (str_contains($request->url(), 'api.blizzard.com')) {
                $query = parse_url($request->url(), PHP_URL_QUERY);
                parse_str($query, $params);

                return isset($params['itemId']) && $params['itemId'] === '19019';
            }

            return true;
        });
    }

    public function test_search_maps_name_parameter(): void
    {
        Http::fake([
            'eu.battle.net/oauth/token' => Http::response([
                'access_token' => 'test_token',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ]),
            'eu.api.blizzard.com/*' => Http::response(['results' => []]),
        ]);

        $client = new Client('client_id', 'client_secret');
        $service = new MediaService($client);

        $service->search(['tags' => ['item'], 'name' => 'Thunderfury']);

        Http::assertSent(function ($request) {
            if (str_contains($request->url(), 'api.blizzard.com')) {
                $query = parse_url($request->url(), PHP_URL_QUERY);
                parse_str($query, $params);

                return isset($params['name.en_US']) && $params['name.en_US'] === 'Thunderfury';
            }

            return true;
        });
    }

    public function test_search_maps_orderby_parameter(): void
    {
        Http::fake([
            'eu.battle.net/oauth/token' => Http::response([
                'access_token' => 'test_token',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ]),
            'eu.api.blizzard.com/*' => Http::response(['results' => []]),
        ]);

        $client = new Client('client_id', 'client_secret');
        $service = new MediaService($client);

        $service->search(['tags' => ['item'], 'orderby' => 'name']);

        Http::assertSent(function ($request) {
            if (str_contains($request->url(), 'api.blizzard.com')) {
                $query = parse_url($request->url(), PHP_URL_QUERY);
                parse_str($query, $params);

                return isset($params['orderby']) && $params['orderby'] === 'name';
            }

            return true;
        });
    }

    public function test_search_maps_page_parameter(): void
    {
        Http::fake([
            'eu.battle.net/oauth/token' => Http::response([
                'access_token' => 'test_token',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ]),
            'eu.api.blizzard.com/*' => Http::response(['results' => []]),
        ]);

        $client = new Client('client_id', 'client_secret');
        $service = new MediaService($client);

        $service->search(['tags' => ['item'], 'page' => 3]);

        Http::assertSent(function ($request) {
            if (str_contains($request->url(), 'api.blizzard.com')) {
                $query = parse_url($request->url(), PHP_URL_QUERY);
                parse_str($query, $params);

                return isset($params['_page']) && $params['_page'] === '3';
            }

            return true;
        });
    }

    public function test_search_maps_page_size_parameter(): void
    {
        Http::fake([
            'eu.battle.net/oauth/token' => Http::response([
                'access_token' => 'test_token',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ]),
            'eu.api.blizzard.com/*' => Http::response(['results' => []]),
        ]);

        $client = new Client('client_id', 'client_secret');
        $service = new MediaService($client);

        $service->search(['tags' => ['item'], 'pageSize' => 50]);

        Http::assertSent(function ($request) {
            if (str_contains($request->url(), 'api.blizzard.com')) {
                $query = parse_url($request->url(), PHP_URL_QUERY);
                parse_str($query, $params);

                return isset($params['_pageSize']) && $params['_pageSize'] === '50';
            }

            return true;
        });
    }

    public function test_search_caps_page_size_at_maximum(): void
    {
        Http::fake([
            'eu.battle.net/oauth/token' => Http::response([
                'access_token' => 'test_token',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ]),
            'eu.api.blizzard.com/*' => Http::response(['results' => []]),
        ]);

        $client = new Client('client_id', 'client_secret');
        $service = new MediaService($client);

        $service->search(['tags' => ['item'], 'pageSize' => 5000]);

        Http::assertSent(function ($request) {
            if (str_contains($request->url(), 'api.blizzard.com')) {
                $query = parse_url($request->url(), PHP_URL_QUERY);
                parse_str($query, $params);

                return isset($params['_pageSize']) && $params['_pageSize'] === '1000';
            }

            return true;
        });
    }

    public function test_search_caches_results(): void
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

        $client = new Client('client_id', 'client_secret');
        $service = new MediaService($client);

        $service->search(['tags' => ['item'], 'name' => 'Thunderfury']);
        $service->search(['tags' => ['item'], 'name' => 'Thunderfury']);

        $this->assertEquals(1, $callCount);
    }

    public function test_search_cache_key_varies_by_parameters(): void
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

        $client = new Client('client_id', 'client_secret');
        $service = new MediaService($client);

        $service->search(['tags' => ['item'], 'name' => 'Thunderfury']);
        $service->search(['tags' => ['item'], 'name' => 'Ashkandi']);

        $this->assertEquals(2, $callCount);
    }

    public function test_with_namespace_affects_cache_keys(): void
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

                return Http::response(['assets' => []]);
            },
        ]);

        $client = new Client('client_id', 'client_secret');
        $service = new MediaService($client);

        $service->find('item', 19019);
        $service->withNamespace('static-classic-eu')->find('item', 19019);

        $this->assertEquals(2, $callCount);
        $this->assertTrue(Cache::has('blizzard.media.item.static-eu.19019'));
        $this->assertTrue(Cache::has('blizzard.media.item.static-classic-eu.19019'));
    }

    public function test_validate_tags_accepts_valid_array(): void
    {
        Http::fake([
            'eu.battle.net/oauth/token' => Http::response([
                'access_token' => 'test_token',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ]),
            'eu.api.blizzard.com/*' => Http::response(['results' => []]),
        ]);

        $client = new Client('client_id', 'client_secret');
        $service = new MediaService($client);

        $results = $service->search(['tags' => ['item', 'spell']]);

        $this->assertIsArray($results);
    }

    public function test_validate_tags_throws_for_multiple_invalid_tags(): void
    {
        $client = new Client('client_id', 'client_secret');
        $service = new MediaService($client);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid tag(s): foo, bar. Allowed tags are: item, spell, playable-class');

        $service->search(['tags' => ['foo', 'bar']]);
    }

    public function test_download_assets_stores_file_on_disk(): void
    {
        Storage::fake('public');

        Http::fake([
            'render.worldofwarcraft.com/*' => Http::response('fake-image-content', 200),
        ]);

        $client = new Client('client_id', 'client_secret');
        $service = new MediaService($client);

        $asset = [
            'key' => 'icon',
            'value' => 'https://render.worldofwarcraft.com/classic-us/icons/56/inv_sword_39.jpg',
            'file_data_id' => 135349,
        ];

        $paths = $service->downloadAssets($asset);

        $this->assertArrayHasKey(135349, $paths);
        $this->assertEquals('blizzard/media/135349.jpg', $paths[135349]);
        Storage::disk('public')->assertExists('blizzard/media/135349.jpg');
        $this->assertEquals('fake-image-content', Storage::disk('public')->get($paths[135349]));
    }

    public function test_download_assets_skips_existing_file(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('blizzard/media/135349.jpg', 'existing-content');

        $httpCallCount = 0;

        Http::fake([
            'render.worldofwarcraft.com/*' => function () use (&$httpCallCount) {
                $httpCallCount++;

                return Http::response('new-content', 200);
            },
        ]);

        $client = new Client('client_id', 'client_secret');
        $service = new MediaService($client);

        $asset = [
            'key' => 'icon',
            'value' => 'https://render.worldofwarcraft.com/classic-us/icons/56/inv_sword_39.jpg',
            'file_data_id' => 135349,
        ];

        $paths = $service->downloadAssets($asset);

        $this->assertEquals('blizzard/media/135349.jpg', $paths[135349]);
        $this->assertEquals(0, $httpCallCount);
        $this->assertEquals('existing-content', Storage::disk('public')->get($paths[135349]));
    }

    public function test_download_assets_returns_null_on_http_failure(): void
    {
        Storage::fake('public');

        Http::fake([
            'render.worldofwarcraft.com/*' => Http::response('Not Found', 404),
        ]);

        $client = new Client('client_id', 'client_secret');
        $service = new MediaService($client);

        $asset = [
            'key' => 'icon',
            'value' => 'https://render.worldofwarcraft.com/classic-us/icons/56/inv_sword_39.jpg',
            'file_data_id' => 135349,
        ];

        $paths = $service->downloadAssets($asset);

        $this->assertNull($paths[135349]);
        Storage::disk('public')->assertMissing('blizzard/media/135349.jpg');
    }

    public function test_download_assets_skips_invalid_assets(): void
    {
        Storage::fake('public');

        $client = new Client('client_id', 'client_secret');
        $service = new MediaService($client);

        $paths = $service->downloadAssets(['key' => 'icon']);

        $this->assertEmpty($paths);
    }

    public function test_download_assets_processes_multiple_assets(): void
    {
        Storage::fake('public');

        Http::fake([
            'render.worldofwarcraft.com/*' => Http::response('fake-image-content', 200),
        ]);

        $client = new Client('client_id', 'client_secret');
        $service = new MediaService($client);

        $assets = [
            [
                'key' => 'icon',
                'value' => 'https://render.worldofwarcraft.com/icons/56/inv_sword_39.jpg',
                'file_data_id' => 135349,
            ],
            [
                'key' => 'thumbnail',
                'value' => 'https://render.worldofwarcraft.com/icons/32/inv_sword_39.jpg',
                'file_data_id' => 135350,
            ],
        ];

        $results = $service->downloadAssets($assets);

        $this->assertCount(2, $results);
        $this->assertEquals('blizzard/media/135349.jpg', $results[135349]);
        $this->assertEquals('blizzard/media/135350.jpg', $results[135350]);
        Storage::disk('public')->assertExists('blizzard/media/135349.jpg');
        Storage::disk('public')->assertExists('blizzard/media/135350.jpg');
    }

    public function test_get_asset_urls_returns_public_urls(): void
    {
        Storage::fake('public');

        Http::fake([
            'render.worldofwarcraft.com/*' => Http::response('fake-image-content', 200),
        ]);

        $client = new Client('client_id', 'client_secret');
        $service = new MediaService($client);

        $asset = [
            'key' => 'icon',
            'value' => 'https://render.worldofwarcraft.com/classic-us/icons/56/inv_sword_39.jpg',
            'file_data_id' => 135349,
        ];

        $urls = $service->getAssetUrls($asset);

        $this->assertArrayHasKey(135349, $urls);
        $this->assertStringContainsString('blizzard/media/135349.jpg', $urls[135349]);
    }

    public function test_get_asset_urls_returns_null_on_failure(): void
    {
        Storage::fake('public');

        Http::fake([
            'render.worldofwarcraft.com/*' => Http::response('Not Found', 404),
        ]);

        $client = new Client('client_id', 'client_secret');
        $service = new MediaService($client);

        $asset = [
            'key' => 'icon',
            'value' => 'https://render.worldofwarcraft.com/classic-us/icons/56/inv_sword_39.jpg',
            'file_data_id' => 135349,
        ];

        $urls = $service->getAssetUrls($asset);

        $this->assertNull($urls[135349]);
    }

    public function test_get_asset_urls_handles_multiple_assets(): void
    {
        Storage::fake('public');

        Http::fake([
            'render.worldofwarcraft.com/*' => Http::response('fake-image-content', 200),
        ]);

        $client = new Client('client_id', 'client_secret');
        $service = new MediaService($client);

        $assets = [
            [
                'key' => 'icon',
                'value' => 'https://render.worldofwarcraft.com/icons/56/inv_sword_39.jpg',
                'file_data_id' => 135349,
            ],
            [
                'key' => 'thumbnail',
                'value' => 'https://render.worldofwarcraft.com/icons/32/inv_sword_39.jpg',
                'file_data_id' => 135350,
            ],
        ];

        $urls = $service->getAssetUrls($assets);

        $this->assertCount(2, $urls);
        $this->assertStringContainsString('blizzard/media/135349.jpg', $urls[135349]);
        $this->assertStringContainsString('blizzard/media/135350.jpg', $urls[135350]);
    }

    public function test_assets_exist_returns_true_when_file_exists(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('blizzard/media/135349.jpg', 'content');

        $client = new Client('client_id', 'client_secret');
        $service = new MediaService($client);

        $asset = [
            'key' => 'icon',
            'value' => 'https://render.worldofwarcraft.com/classic-us/icons/56/inv_sword_39.jpg',
            'file_data_id' => 135349,
        ];

        $exists = $service->assetsExist($asset);

        $this->assertTrue($exists[135349]);
    }

    public function test_assets_exist_returns_false_when_file_missing(): void
    {
        Storage::fake('public');

        $client = new Client('client_id', 'client_secret');
        $service = new MediaService($client);

        $asset = [
            'key' => 'icon',
            'value' => 'https://render.worldofwarcraft.com/classic-us/icons/56/inv_sword_39.jpg',
            'file_data_id' => 135349,
        ];

        $exists = $service->assetsExist($asset);

        $this->assertFalse($exists[135349]);
    }

    public function test_assets_exist_handles_multiple_assets(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('blizzard/media/135349.jpg', 'content');

        $client = new Client('client_id', 'client_secret');
        $service = new MediaService($client);

        $assets = [
            [
                'key' => 'icon',
                'value' => 'https://render.worldofwarcraft.com/icons/56/inv_sword_39.jpg',
                'file_data_id' => 135349,
            ],
            [
                'key' => 'thumbnail',
                'value' => 'https://render.worldofwarcraft.com/icons/32/inv_sword_39.jpg',
                'file_data_id' => 135350,
            ],
        ];

        $exists = $service->assetsExist($assets);

        $this->assertTrue($exists[135349]);
        $this->assertFalse($exists[135350]);
    }

    public function test_extract_extension_handles_various_urls(): void
    {
        Storage::fake('public');

        Http::fake([
            '*' => Http::response('fake-image-content', 200),
        ]);

        $client = new Client('client_id', 'client_secret');
        $service = new MediaService($client);

        $asset = [
            'key' => 'icon',
            'value' => 'https://render.worldofwarcraft.com/icons/56/inv_sword_39.png',
            'file_data_id' => 111111,
        ];

        $paths = $service->downloadAssets($asset);

        $this->assertEquals('blizzard/media/111111.png', $paths[111111]);
    }

    public function test_download_uses_configured_filesystem(): void
    {
        Storage::fake('custom_disk');

        config(['services.blizzard.filesystem' => 'custom_disk']);

        Http::fake([
            'render.worldofwarcraft.com/*' => Http::response('fake-image-content', 200),
        ]);

        $client = new Client('client_id', 'client_secret');
        $service = new MediaService($client);

        $asset = [
            'key' => 'icon',
            'value' => 'https://render.worldofwarcraft.com/classic-us/icons/56/inv_sword_39.jpg',
            'file_data_id' => 135349,
        ];

        $service->downloadAssets($asset);

        Storage::disk('custom_disk')->assertExists('blizzard/media/135349.jpg');
    }
}
