<?php

namespace Tests\Unit\Services\Blizzard;

use App\Services\Blizzard\Client;
use App\Services\Blizzard\MediaService;
use App\Services\Blizzard\PlayableClassService;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class PlayableClassServiceTest extends TestCase
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

    public function test_constructor_sets_static_classic_eu_namespace(): void
    {
        $client = new Client('client_id', 'client_secret');
        new PlayableClassService($client);

        $this->assertEquals('static-classicann-eu', $client->getNamespace());
    }

    public function test_index_returns_playable_class_data(): void
    {
        Http::fake([
            'eu.battle.net/oauth/token' => Http::response([
                'access_token' => 'test_token',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ]),
            'eu.api.blizzard.com/*' => Http::response([
                'playable_classes' => [
                    ['id' => 1, 'name' => 'Warrior'],
                    ['id' => 2, 'name' => 'Paladin'],
                ],
            ]),
        ]);

        $client = new Client('client_id', 'client_secret', namespace: 'static-classicann-eu');
        $service = new PlayableClassService($client);

        $result = $service->index();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('playable_classes', $result);
        $this->assertCount(2, $result['playable_classes']);
    }

    public function test_index_makes_correct_api_call(): void
    {
        Http::fake([
            'eu.battle.net/oauth/token' => Http::response([
                'access_token' => 'test_token',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ]),
            'eu.api.blizzard.com/*' => Http::response(['playable_classes' => []]),
        ]);

        $client = new Client('client_id', 'client_secret', namespace: 'static-classicann-eu');
        $service = new PlayableClassService($client);

        $service->index();

        Http::assertSent(function ($request) {
            if (str_contains($request->url(), 'api.blizzard.com')) {
                return str_contains($request->url(), '/data/wow/media/playable-class/index');
            }

            return true;
        });
    }

    public function test_index_caches_result(): void
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

                return Http::response(['playable_classes' => []]);
            },
        ]);

        $client = new Client('client_id', 'client_secret', namespace: 'static-classicann-eu');
        $service = new PlayableClassService($client);

        $service->index();
        $service->index();
        $service->index();

        $this->assertEquals(1, $callCount);
    }

    public function test_index_uses_namespace_in_cache_key(): void
    {
        Http::fake([
            'eu.battle.net/oauth/token' => Http::response([
                'access_token' => 'test_token',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ]),
            'eu.api.blizzard.com/*' => Http::response(['playable_classes' => []]),
        ]);

        $client = new Client('client_id', 'client_secret', namespace: 'static-classicann-eu');
        $service = new PlayableClassService($client);

        $service->index();

        $this->assertTrue(Cache::has('blizzard.playable-class.index.static-classicann-eu'));
    }

    public function test_index_throws_exception_on_api_error(): void
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
        $service = new PlayableClassService($client);

        $this->expectException(RequestException::class);

        $service->index();
    }

    public function test_index_fresh_bypasses_cache(): void
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

                return Http::response(['playable_classes' => []]);
            },
        ]);

        $client = new Client('client_id', 'client_secret', namespace: 'static-classicann-eu');
        $service = new PlayableClassService($client);

        $service->index();
        $service->fresh()->index();

        $this->assertEquals(2, $callCount);
    }

    public function test_find_returns_playable_class_data(): void
    {
        Http::fake([
            'eu.battle.net/oauth/token' => Http::response([
                'access_token' => 'test_token',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ]),
            'eu.api.blizzard.com/*' => Http::response([
                'id' => 1,
                'name' => 'Warrior',
                'power_type' => ['name' => 'Rage'],
            ]),
        ]);

        $client = new Client('client_id', 'client_secret', namespace: 'static-classicann-eu');
        $service = new PlayableClassService($client);

        $result = $service->find(1);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('id', $result);
        $this->assertArrayHasKey('name', $result);
        $this->assertEquals(1, $result['id']);
        $this->assertEquals('Warrior', $result['name']);
    }

    public function test_find_makes_correct_api_call(): void
    {
        Http::fake([
            'eu.battle.net/oauth/token' => Http::response([
                'access_token' => 'test_token',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ]),
            'eu.api.blizzard.com/*' => Http::response(['id' => 1, 'name' => 'Warrior']),
        ]);

        $client = new Client('client_id', 'client_secret', namespace: 'static-classicann-eu');
        $service = new PlayableClassService($client);

        $service->find(1);

        Http::assertSent(function ($request) {
            if (str_contains($request->url(), 'api.blizzard.com')) {
                return str_contains($request->url(), '/data/wow/playable-class/1');
            }

            return true;
        });
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

                return Http::response(['id' => 1, 'name' => 'Warrior']);
            },
        ]);

        $client = new Client('client_id', 'client_secret', namespace: 'static-classicann-eu');
        $service = new PlayableClassService($client);

        $service->find(1);
        $service->find(1);
        $service->find(1);

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
            'eu.api.blizzard.com/*' => Http::response(['id' => 1, 'name' => 'Warrior']),
        ]);

        $client = new Client('client_id', 'client_secret', namespace: 'static-classicann-eu');
        $service = new PlayableClassService($client);

        $service->find(1);

        $this->assertTrue(Cache::has('blizzard.playable-class.1.static-classicann-eu'));
    }

    public function test_find_throws_exception_on_api_error(): void
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
        $service = new PlayableClassService($client);

        $this->expectException(RequestException::class);

        $service->find(999);
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

                return Http::response(['id' => 1, 'name' => 'Warrior']);
            },
        ]);

        $client = new Client('client_id', 'client_secret', namespace: 'static-classicann-eu');
        $service = new PlayableClassService($client);

        $service->find(1);
        $service->fresh()->find(1);

        $this->assertEquals(2, $callCount);
    }

    public function test_find_caches_different_ids_separately(): void
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

                return Http::response(['id' => 1, 'name' => 'Warrior']);
            },
        ]);

        $client = new Client('client_id', 'client_secret', namespace: 'static-classicann-eu');
        $service = new PlayableClassService($client);

        $service->find(1);
        $service->find(2);

        $this->assertEquals(2, $callCount);
        $this->assertTrue(Cache::has('blizzard.playable-class.1.static-classicann-eu'));
        $this->assertTrue(Cache::has('blizzard.playable-class.2.static-classicann-eu'));
    }

    public function test_media_returns_icon_data(): void
    {
        $expectedMedia = [
            'assets' => [
                ['key' => 'icon', 'value' => 'https://render.worldofwarcraft.com/icons/warrior.jpg'],
            ],
        ];

        $this->instance(
            MediaService::class,
            $this->createMockMediaService(function (MockInterface $mock) use ($expectedMedia) {
                $mock->shouldReceive('find')
                    ->once()
                    ->with('playable-class', 1)
                    ->andReturn($expectedMedia);
            })
        );

        $client = new Client('client_id', 'client_secret', namespace: 'static-classicann-eu');
        $service = new PlayableClassService($client);

        $media = $service->media(1);

        $this->assertIsArray($media);
        $this->assertArrayHasKey('assets', $media);
    }

    public function test_media_delegates_to_media_service(): void
    {
        $this->instance(
            MediaService::class,
            $this->createMockMediaService(function (MockInterface $mock) {
                $mock->shouldReceive('find')
                    ->once()
                    ->with('playable-class', 1)
                    ->andReturn(['assets' => []]);
            })
        );

        $client = new Client('client_id', 'client_secret', namespace: 'static-classicann-eu');
        $service = new PlayableClassService($client);

        $service->media(1);
    }

    public function test_icon_url_returns_url_for_valid_class(): void
    {
        $media = [
            'assets' => [
                ['file_data_id' => 123, 'key' => 'icon', 'value' => 'https://render.worldofwarcraft.com/eu/icons/warrior.jpg'],
            ],
        ];

        $this->instance(
            MediaService::class,
            $this->createMockMediaService(function (MockInterface $mock) use ($media) {
                $mock->shouldReceive('find')
                    ->once()
                    ->with('playable-class', 1)
                    ->andReturn($media);
                $mock->shouldReceive('getAssetUrls')
                    ->once()
                    ->with($media['assets'])
                    ->andReturn([123 => 'https://example.com/blizzard/media/123.jpg']);
            })
        );

        $client = new Client('client_id', 'client_secret', namespace: 'static-classicann-eu');
        $service = new PlayableClassService($client);

        $result = $service->iconUrl(1);

        $this->assertEquals('https://example.com/blizzard/media/123.jpg', $result);
    }

    public function test_icon_url_returns_null_when_assets_are_empty(): void
    {
        $media = ['assets' => []];

        $this->instance(
            MediaService::class,
            $this->createMockMediaService(function (MockInterface $mock) use ($media) {
                $mock->shouldReceive('find')
                    ->once()
                    ->with('playable-class', 1)
                    ->andReturn($media);
                $mock->shouldReceive('getAssetUrls')
                    ->once()
                    ->with([])
                    ->andReturn([]);
            })
        );

        $client = new Client('client_id', 'client_secret', namespace: 'static-classicann-eu');
        $service = new PlayableClassService($client);

        $this->assertNull($service->iconUrl(1));
    }

    public function test_icon_url_returns_null_when_assets_key_is_missing(): void
    {
        $this->instance(
            MediaService::class,
            $this->createMockMediaService(function (MockInterface $mock) {
                $mock->shouldReceive('find')
                    ->once()
                    ->with('playable-class', 1)
                    ->andReturn([]);
                $mock->shouldReceive('getAssetUrls')
                    ->once()
                    ->with([])
                    ->andReturn([]);
            })
        );

        $client = new Client('client_id', 'client_secret', namespace: 'static-classicann-eu');
        $service = new PlayableClassService($client);

        $this->assertNull($service->iconUrl(1));
    }

    public function test_icon_url_returns_null_when_asset_download_fails(): void
    {
        $media = [
            'assets' => [
                ['file_data_id' => 123, 'key' => 'icon', 'value' => 'https://render.worldofwarcraft.com/eu/icons/warrior.jpg'],
            ],
        ];

        $this->instance(
            MediaService::class,
            $this->createMockMediaService(function (MockInterface $mock) use ($media) {
                $mock->shouldReceive('find')
                    ->once()
                    ->with('playable-class', 1)
                    ->andReturn($media);
                $mock->shouldReceive('getAssetUrls')
                    ->once()
                    ->with($media['assets'])
                    ->andReturn([123 => null]);
            })
        );

        $client = new Client('client_id', 'client_secret', namespace: 'static-classicann-eu');
        $service = new PlayableClassService($client);

        $this->assertNull($service->iconUrl(1));
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

                return Http::response(['playable_classes' => []]);
            },
        ]);

        $client = new Client('client_id', 'client_secret', namespace: 'static-classicann-eu');
        $service = new PlayableClassService($client);

        $service->index();
        $service->withNamespace('static-classic1x-eu')->index();

        $this->assertEquals(2, $callCount);
        $this->assertTrue(Cache::has('blizzard.playable-class.index.static-classicann-eu'));
        $this->assertTrue(Cache::has('blizzard.playable-class.index.static-classic1x-eu'));
    }
}
