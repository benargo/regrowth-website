<?php

namespace Tests\Unit\Services\Blizzard;

use App\Services\Blizzard\Client;
use App\Services\Blizzard\MediaService;
use App\Services\Blizzard\PlayableRaceService;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class PlayableRaceServiceTest extends TestCase
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
        new PlayableRaceService($client);

        $this->assertEquals('static-classicann-eu', $client->getNamespace());
    }

    public function test_index_returns_playable_race_data(): void
    {
        Http::fake([
            'eu.battle.net/oauth/token' => Http::response([
                'access_token' => 'test_token',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ]),
            'eu.api.blizzard.com/*' => Http::response([
                'playable_races' => [
                    ['id' => 1, 'name' => 'Warrior'],
                    ['id' => 2, 'name' => 'Paladin'],
                ],
            ]),
        ]);

        $client = new Client('client_id', 'client_secret', namespace: 'static-classicann-eu');
        $service = new PlayableRaceService($client);

        $result = $service->index();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('playable_races', $result);
        $this->assertCount(2, $result['playable_races']);
    }

    public function test_index_makes_correct_api_call(): void
    {
        Http::fake([
            'eu.battle.net/oauth/token' => Http::response([
                'access_token' => 'test_token',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ]),
            'eu.api.blizzard.com/*' => Http::response(['playable_races' => []]),
        ]);

        $client = new Client('client_id', 'client_secret', namespace: 'static-classicann-eu');
        $service = new PlayableRaceService($client);

        $service->index();

        Http::assertSent(function ($request) {
            if (str_contains($request->url(), 'api.blizzard.com')) {
                return str_contains($request->url(), '/data/wow/media/playable-race/index');
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

                return Http::response(['playable_races' => []]);
            },
        ]);

        $client = new Client('client_id', 'client_secret', namespace: 'static-classicann-eu');
        $service = new PlayableRaceService($client);

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
            'eu.api.blizzard.com/*' => Http::response(['playable_races' => []]),
        ]);

        $client = new Client('client_id', 'client_secret', namespace: 'static-classicann-eu');
        $service = new PlayableRaceService($client);

        $service->index();

        $this->assertTrue(Cache::has('blizzard.playable-race.index.static-classicann-eu'));
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
        $service = new PlayableRaceService($client);

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

                return Http::response(['playable_races' => []]);
            },
        ]);

        $client = new Client('client_id', 'client_secret', namespace: 'static-classicann-eu');
        $service = new PlayableRaceService($client);

        $service->index();
        $service->fresh()->index();

        $this->assertEquals(2, $callCount);
    }

    public function test_find_returns_playable_race_data(): void
    {
        Http::fake([
            'eu.battle.net/oauth/token' => Http::response([
                'access_token' => 'test_token',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ]),
            'eu.api.blizzard.com/*' => Http::response([
                'id' => 1,
                'name' => 'Orc',
                'faction' => ['type' => 'HORDE', 'name' => 'Horde'],
            ]),
        ]);

        $client = new Client('client_id', 'client_secret', namespace: 'static-classicann-eu');
        $service = new PlayableRaceService($client);

        $result = $service->find(1);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('id', $result);
        $this->assertArrayHasKey('name', $result);
        $this->assertEquals(1, $result['id']);
        $this->assertEquals('Orc', $result['name']);
    }

    public function test_find_makes_correct_api_call(): void
    {
        Http::fake([
            'eu.battle.net/oauth/token' => Http::response([
                'access_token' => 'test_token',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ]),
            'eu.api.blizzard.com/*' => Http::response(['id' => 1, 'name' => 'Orc']),
        ]);

        $client = new Client('client_id', 'client_secret', namespace: 'static-classicann-eu');
        $service = new PlayableRaceService($client);

        $service->find(1);

        Http::assertSent(function ($request) {
            if (str_contains($request->url(), 'api.blizzard.com')) {
                return str_contains($request->url(), '/data/wow/playable-race/1');
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
        $service = new PlayableRaceService($client);

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
            'eu.api.blizzard.com/*' => Http::response(['id' => 2, 'name' => 'Orc']),
        ]);

        $client = new Client('client_id', 'client_secret', namespace: 'static-classicann-eu');
        $service = new PlayableRaceService($client);

        $service->find(2);

        $this->assertTrue(Cache::has('blizzard.playable-race.2.static-classicann-eu'));
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
        $service = new PlayableRaceService($client);

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

                return Http::response(['id' => 2, 'name' => 'Orc']);
            },
        ]);

        $client = new Client('client_id', 'client_secret', namespace: 'static-classicann-eu');
        $service = new PlayableRaceService($client);

        $service->find(2);
        $service->fresh()->find(2);

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

                return Http::response(['id' => 2, 'name' => 'Orc']);
            },
        ]);

        $client = new Client('client_id', 'client_secret', namespace: 'static-classicann-eu');
        $service = new PlayableRaceService($client);

        $service->find(1);
        $service->find(2);

        $this->assertEquals(2, $callCount);
        $this->assertTrue(Cache::has('blizzard.playable-race.1.static-classicann-eu'));
        $this->assertTrue(Cache::has('blizzard.playable-race.2.static-classicann-eu'));
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

                return Http::response(['playable_races' => []]);
            },
        ]);

        $client = new Client('client_id', 'client_secret', namespace: 'static-classicann-eu');
        $service = new PlayableRaceService($client);

        $service->index();
        $service->withNamespace('static-classic1x-eu')->index();

        $this->assertEquals(2, $callCount);
        $this->assertTrue(Cache::has('blizzard.playable-race.index.static-classicann-eu'));
        $this->assertTrue(Cache::has('blizzard.playable-race.index.static-classic1x-eu'));
    }
}
