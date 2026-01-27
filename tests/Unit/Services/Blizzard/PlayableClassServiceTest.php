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

        $this->assertEquals('static-classic-eu', $client->getNamespace());
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

        $client = new Client('client_id', 'client_secret', namespace: 'static-classic-eu');
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

        $client = new Client('client_id', 'client_secret', namespace: 'static-classic-eu');
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

        $client = new Client('client_id', 'client_secret', namespace: 'static-classic-eu');
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

        $client = new Client('client_id', 'client_secret', namespace: 'static-classic-eu');
        $service = new PlayableClassService($client);

        $service->index();

        $this->assertTrue(Cache::has('blizzard.playable-class.index.static-classic-eu'));
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

        $client = new Client('client_id', 'client_secret', namespace: 'static-classic-eu');
        $service = new PlayableClassService($client);

        $service->index();
        $service->indexFresh();

        $this->assertEquals(2, $callCount);
    }

    public function test_index_fresh_clears_existing_cache(): void
    {
        Http::fake([
            'eu.battle.net/oauth/token' => Http::response([
                'access_token' => 'test_token',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ]),
            'eu.api.blizzard.com/*' => Http::response(['playable_classes' => []]),
        ]);

        $client = new Client('client_id', 'client_secret', namespace: 'static-classic-eu');
        $service = new PlayableClassService($client);

        $service->index();
        $this->assertTrue(Cache::has('blizzard.playable-class.index.static-classic-eu'));

        Cache::shouldReceive('forget')
            ->once()
            ->with('blizzard.playable-class.index.static-classic-eu');

        Cache::shouldReceive('get')
            ->with('blizzard_access_token_eu')
            ->andReturn('test_token');

        Http::fake([
            'eu.battle.net/oauth/token' => Http::response([
                'access_token' => 'test_token',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ]),
            'eu.api.blizzard.com/*' => Http::response(['playable_classes' => []]),
        ]);

        $service->indexFresh();
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

        $client = new Client('client_id', 'client_secret', namespace: 'static-classic-eu');
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

        $client = new Client('client_id', 'client_secret', namespace: 'static-classic-eu');
        $service = new PlayableClassService($client);

        $service->media(1);
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

        $client = new Client('client_id', 'client_secret', namespace: 'static-classic-eu');
        $service = new PlayableClassService($client);

        $service->index();
        $service->withNamespace('static-classic1x-eu')->index();

        $this->assertEquals(2, $callCount);
        $this->assertTrue(Cache::has('blizzard.playable-class.index.static-classic-eu'));
        $this->assertTrue(Cache::has('blizzard.playable-class.index.static-classic1x-eu'));
    }
}
