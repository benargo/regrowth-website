<?php

namespace Tests\Unit\Services\Blizzard;

use App\Events\GuildRosterFetched;
use App\Services\Blizzard\BlizzardService;
use App\Services\Blizzard\Client;
use App\Services\Blizzard\Exceptions\InvalidClassException;
use App\Services\Blizzard\Exceptions\InvalidRaceException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BlizzardServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Http::fake([
            'eu.battle.net/oauth/token' => Http::response([
                'access_token' => 'test_token',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ]),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function makeConfig(array $overrides = []): array
    {
        return array_replace_recursive([
            'region' => 'eu',
            'locale' => 'en_GB',
            'realm' => ['slug' => 'thunderstrike'],
            'guild' => ['slug' => 'regrowth'],
            'namespaces' => [
                'profile' => 'profile-classicann-eu',
                'static' => 'static-classicann-eu',
                'media' => 'static-eu',
            ],
            'filesystem' => 'public',
        ], $overrides);
    }

    private function makeService(?array $config = null): BlizzardService
    {
        return new BlizzardService(
            new Client('test_id', 'test_secret'),
            $config ?? $this->makeConfig(),
        );
    }

    // ==================== Constructor ====================

    #[Test]
    public function constructor_resolves_namespace_config_values(): void
    {
        $service = $this->makeService();

        // Verify the service was constructed without throwing
        $this->assertInstanceOf(BlizzardService::class, $service);
    }

    #[Test]
    public function constructor_throws_when_profile_namespace_missing(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing Blizzard config key: namespaces.profile');

        $config = $this->makeConfig();
        Arr::forget($config, 'namespaces.profile');

        $this->makeService($config);
    }

    #[Test]
    public function constructor_throws_when_static_namespace_missing(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing Blizzard config key: namespaces.static');

        $config = $this->makeConfig();
        Arr::forget($config, 'namespaces.static');

        $this->makeService($config);
    }

    #[Test]
    public function constructor_throws_when_media_namespace_missing(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing Blizzard config key: namespaces.media');

        $config = $this->makeConfig();
        Arr::forget($config, 'namespaces.media');

        $this->makeService($config);
    }

    // ==================== Cache Key ====================

    #[Test]
    public function cache_key_returns_deterministic_key(): void
    {
        $service = $this->makeService();

        $key1 = $service->cacheKey('findItem', 12345);
        $key2 = $service->cacheKey('findItem', 12345);

        $this->assertSame($key1, $key2);
        $this->assertStringStartsWith('blizzard.findItem.', $key1);
    }

    #[Test]
    public function cache_key_varies_by_method_name(): void
    {
        $service = $this->makeService();

        $key1 = $service->cacheKey('findItem', 100);
        $key2 = $service->cacheKey('getCharacterProfile', 100);

        $this->assertNotSame($key1, $key2);
    }

    #[Test]
    public function cache_key_varies_by_parameters(): void
    {
        $service = $this->makeService();

        $key1 = $service->cacheKey('findItem', 100);
        $key2 = $service->cacheKey('findItem', 200);

        $this->assertNotSame($key1, $key2);
    }

    #[Test]
    public function cache_key_with_no_params_is_consistent(): void
    {
        $service = $this->makeService();

        $key1 = $service->cacheKey('getGuildRoster');
        $key2 = $service->cacheKey('getGuildRoster');

        $this->assertSame($key1, $key2);
        $this->assertStringStartsWith('blizzard.getGuildRoster.', $key1);
    }

    #[Test]
    public function cache_key_with_multiple_params(): void
    {
        $service = $this->makeService();

        $key1 = $service->cacheKey('getGuildRoster', 'thunderstrike', 'regrowth');
        $key2 = $service->cacheKey('getGuildRoster', 'thunderstrike', 'other-guild');

        $this->assertNotSame($key1, $key2);
    }

    // ==================== Characters ====================

    #[Test]
    public function get_character_profile_returns_character_data(): void
    {
        Http::fake([
            'eu.api.blizzard.com/*' => Http::response([
                'id' => 12345,
                'name' => 'Testchar',
                'level' => 60,
                'character_class' => ['name' => 'Warrior'],
            ]),
        ]);

        $service = $this->makeService();
        $profile = $service->getCharacterProfile('Testchar', 'thunderstrike');

        $this->assertIsArray($profile);
        $this->assertEquals(12345, $profile['id']);
        $this->assertEquals('Testchar', $profile['name']);
        $this->assertEquals(60, $profile['level']);
    }

    #[Test]
    public function get_character_profile_caches_result(): void
    {
        $callCount = 0;

        Http::fake([
            'eu.api.blizzard.com/*' => function () use (&$callCount) {
                $callCount++;

                return Http::response(['id' => 12345, 'name' => 'Testchar']);
            },
        ]);

        $service = $this->makeService();
        $service->getCharacterProfile('Testchar', 'thunderstrike');
        $service->getCharacterProfile('Testchar', 'thunderstrike');
        $service->getCharacterProfile('Testchar', 'thunderstrike');

        $this->assertEquals(1, $callCount);
    }

    #[Test]
    public function get_character_profile_uses_standardised_cache_key(): void
    {
        Http::fake([
            'eu.api.blizzard.com/*' => Http::response(['id' => 12345, 'name' => 'Testchar']),
        ]);

        $service = $this->makeService();
        $service->getCharacterProfile('Testchar', 'thunderstrike');

        $this->assertTrue(Cache::tags(['blizzard', 'blizzard-api-response'])->has($service->cacheKey('getCharacterProfile', 'thunderstrike', 'testchar')));
    }

    #[Test]
    public function get_character_profile_uses_default_realm_when_not_provided(): void
    {
        Http::fake([
            'eu.api.blizzard.com/*' => Http::response(['id' => 12345, 'name' => 'Testchar']),
        ]);

        $service = $this->makeService();
        $service->getCharacterProfile('Testchar');

        Http::assertSent(function ($request) {
            if (str_contains($request->url(), 'api.blizzard.com')) {
                return str_contains($request->url(), '/thunderstrike/testchar');
            }

            return true;
        });
    }

    #[Test]
    public function get_character_profile_uses_provided_realm(): void
    {
        Http::fake([
            'eu.api.blizzard.com/*' => Http::response(['id' => 12345, 'name' => 'Testchar']),
        ]);

        $service = $this->makeService();
        $service->getCharacterProfile('Testchar', 'wild-growth');

        Http::assertSent(function ($request) {
            if (str_contains($request->url(), 'api.blizzard.com')) {
                return str_contains($request->url(), '/wild-growth/testchar');
            }

            return true;
        });
    }

    #[Test]
    public function get_character_profile_converts_name_to_lowercase(): void
    {
        Http::fake([
            'eu.api.blizzard.com/*' => Http::response(['id' => 12345, 'name' => 'TestChar']),
        ]);

        $service = $this->makeService();
        $service->getCharacterProfile('TestChar', 'thunderstrike');

        Http::assertSent(function ($request) {
            if (str_contains($request->url(), 'api.blizzard.com')) {
                return str_contains($request->url(), '/testchar');
            }

            return true;
        });
    }

    #[Test]
    public function get_character_profile_sends_profile_namespace_header(): void
    {
        Http::fake([
            'eu.api.blizzard.com/*' => Http::response(['id' => 12345, 'name' => 'Testchar']),
        ]);

        $service = $this->makeService();
        $service->getCharacterProfile('Testchar');

        Http::assertSent(function ($request) {
            if (str_contains($request->url(), 'api.blizzard.com')) {
                return $request->header('Battlenet-Namespace')[0] === 'profile-classicann-eu';
            }

            return true;
        });
    }

    #[Test]
    public function get_character_profile_throws_on_api_error(): void
    {
        Http::fake([
            'eu.api.blizzard.com/*' => Http::response(['error' => 'Not Found'], 404),
        ]);

        $service = $this->makeService();

        $this->expectException(RequestException::class);

        $service->getCharacterProfile('NonExistentChar', 'thunderstrike');
    }

    #[Test]
    public function get_character_status_returns_status_data(): void
    {
        Http::fake([
            'eu.api.blizzard.com/*' => Http::response([
                'id' => 12345,
                'is_valid' => true,
            ]),
        ]);

        $service = $this->makeService();
        $status = $service->getCharacterStatus('Testchar', 'thunderstrike');

        $this->assertIsArray($status);
        $this->assertEquals(12345, $status['id']);
        $this->assertTrue($status['is_valid']);
    }

    #[Test]
    public function get_character_status_caches_result(): void
    {
        $callCount = 0;

        Http::fake([
            'eu.api.blizzard.com/*' => function () use (&$callCount) {
                $callCount++;

                return Http::response(['id' => 12345, 'is_valid' => true]);
            },
        ]);

        $service = $this->makeService();
        $service->getCharacterStatus('Testchar', 'thunderstrike');
        $service->getCharacterStatus('Testchar', 'thunderstrike');

        $this->assertEquals(1, $callCount);
    }

    #[Test]
    public function get_character_status_uses_standardised_cache_key(): void
    {
        Http::fake([
            'eu.api.blizzard.com/*' => Http::response(['id' => 12345, 'is_valid' => true]),
        ]);

        $service = $this->makeService();
        $service->getCharacterStatus('Testchar', 'thunderstrike');

        $this->assertTrue(Cache::tags(['blizzard', 'blizzard-api-response'])->has($service->cacheKey('getCharacterStatus', 'thunderstrike', 'testchar')));
    }

    #[Test]
    public function get_character_status_builds_correct_endpoint(): void
    {
        Http::fake([
            'eu.api.blizzard.com/*' => Http::response(['id' => 12345, 'is_valid' => true]),
        ]);

        $service = $this->makeService();
        $service->getCharacterStatus('Testchar', 'thunderstrike');

        Http::assertSent(function ($request) {
            if (str_contains($request->url(), 'api.blizzard.com')) {
                return str_contains($request->url(), '/thunderstrike/testchar/status');
            }

            return true;
        });
    }

    // ==================== Guild ====================

    #[Test]
    public function get_guild_roster_returns_roster_data(): void
    {
        Http::fake([
            'eu.api.blizzard.com/*' => Http::response([
                'members' => [
                    ['character' => ['id' => 1, 'name' => 'TestChar'], 'rank' => 0],
                ],
            ]),
        ]);

        Event::fake([GuildRosterFetched::class]);

        $service = $this->makeService();
        $result = $service->getGuildRoster();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('members', $result);
        $this->assertCount(1, $result['members']);
    }

    #[Test]
    public function get_guild_roster_makes_correct_api_call(): void
    {
        Http::fake([
            'eu.api.blizzard.com/*' => Http::response(['members' => []]),
        ]);

        Event::fake([GuildRosterFetched::class]);

        $service = $this->makeService();
        $service->getGuildRoster();

        Http::assertSent(function ($request) {
            if (str_contains($request->url(), 'api.blizzard.com')) {
                return str_contains($request->url(), '/data/wow/guild/thunderstrike/regrowth/roster')
                    && $request->header('Battlenet-Namespace')[0] === 'profile-classicann-eu';
            }

            return true;
        });
    }

    #[Test]
    public function get_guild_roster_dispatches_guild_roster_fetched_event(): void
    {
        Http::fake([
            'eu.api.blizzard.com/*' => Http::response(['members' => []]),
        ]);

        Event::fake([GuildRosterFetched::class]);

        $service = $this->makeService();
        $service->getGuildRoster();

        Event::assertDispatched(GuildRosterFetched::class);
    }

    #[Test]
    public function get_guild_roster_caches_result(): void
    {
        $callCount = 0;

        Http::fake([
            'eu.api.blizzard.com/*' => function () use (&$callCount) {
                $callCount++;

                return Http::response(['members' => []]);
            },
        ]);

        Event::fake([GuildRosterFetched::class]);

        $service = $this->makeService();
        $service->getGuildRoster();
        $service->getGuildRoster();

        $this->assertEquals(1, $callCount);
    }

    #[Test]
    public function get_guild_roster_uses_standardised_cache_key(): void
    {
        Http::fake([
            'eu.api.blizzard.com/*' => Http::response(['members' => []]),
        ]);

        Event::fake([GuildRosterFetched::class]);

        $service = $this->makeService();
        $service->getGuildRoster();

        $this->assertTrue(Cache::tags(['blizzard', 'blizzard-api-response'])->has($service->cacheKey('getGuildRoster', 'thunderstrike', 'regrowth')));
    }

    // ==================== Playable Races ====================

    #[Test]
    public function get_playable_races_returns_race_data(): void
    {
        Http::fake([
            'eu.api.blizzard.com/*' => Http::response([
                'races' => [
                    ['id' => 1, 'name' => 'Human'],
                    ['id' => 2, 'name' => 'Orc'],
                ],
            ]),
        ]);

        $service = $this->makeService();
        $result = $service->getPlayableRaces();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('races', $result);
        $this->assertCount(2, $result['races']);
    }

    #[Test]
    public function get_playable_races_makes_correct_api_call(): void
    {
        Http::fake([
            'eu.api.blizzard.com/*' => Http::response(['races' => []]),
        ]);

        $service = $this->makeService();
        $service->getPlayableRaces();

        Http::assertSent(function ($request) {
            if (str_contains($request->url(), 'api.blizzard.com')) {
                return str_contains($request->url(), '/data/wow/playable-race/index')
                    && $request->header('Battlenet-Namespace')[0] === 'static-classicann-eu';
            }

            return true;
        });
    }

    #[Test]
    public function get_playable_races_caches_result(): void
    {
        $callCount = 0;

        Http::fake([
            'eu.api.blizzard.com/*' => function () use (&$callCount) {
                $callCount++;

                return Http::response(['races' => []]);
            },
        ]);

        $service = $this->makeService();
        $service->getPlayableRaces();
        $service->getPlayableRaces();
        $service->getPlayableRaces();

        $this->assertEquals(1, $callCount);
    }

    #[Test]
    public function get_playable_races_uses_standardised_cache_key(): void
    {
        Http::fake([
            'eu.api.blizzard.com/*' => Http::response(['races' => []]),
        ]);

        $service = $this->makeService();
        $service->getPlayableRaces();

        $this->assertTrue(Cache::tags(['blizzard', 'blizzard-api-response'])->has($service->cacheKey('getPlayableRaces')));
    }

    #[Test]
    public function get_playable_races_throws_on_api_error(): void
    {
        Http::fake([
            'eu.api.blizzard.com/*' => Http::response(['error' => 'Not Found'], 404),
        ]);

        $service = $this->makeService();

        $this->expectException(RequestException::class);

        $service->getPlayableRaces();
    }

    #[Test]
    public function find_playable_race_returns_race_data(): void
    {
        Http::fake([
            'eu.api.blizzard.com/*' => Http::response([
                'id' => 2,
                'name' => 'Orc',
                'faction' => ['type' => 'HORDE', 'name' => 'Horde'],
            ]),
        ]);

        $service = $this->makeService();
        $result = $service->findPlayableRace(2);

        $this->assertIsArray($result);
        $this->assertEquals(2, $result['id']);
        $this->assertEquals('Orc', $result['name']);
    }

    #[Test]
    public function find_playable_race_makes_correct_api_call(): void
    {
        Http::fake([
            'eu.api.blizzard.com/*' => Http::response(['id' => 2, 'name' => 'Orc']),
        ]);

        $service = $this->makeService();
        $service->findPlayableRace(2);

        Http::assertSent(function ($request) {
            if (str_contains($request->url(), 'api.blizzard.com')) {
                return str_contains($request->url(), '/data/wow/playable-race/2')
                    && $request->header('Battlenet-Namespace')[0] === 'static-classicann-eu';
            }

            return true;
        });
    }

    #[Test]
    public function find_playable_race_caches_result(): void
    {
        $callCount = 0;

        Http::fake([
            'eu.api.blizzard.com/*' => function () use (&$callCount) {
                $callCount++;

                return Http::response(['id' => 2, 'name' => 'Orc']);
            },
        ]);

        $service = $this->makeService();
        $service->findPlayableRace(2);
        $service->findPlayableRace(2);
        $service->findPlayableRace(2);

        $this->assertEquals(1, $callCount);
    }

    #[Test]
    public function find_playable_race_uses_standardised_cache_key(): void
    {
        Http::fake([
            'eu.api.blizzard.com/*' => Http::response(['id' => 2, 'name' => 'Orc']),
        ]);

        $service = $this->makeService();
        $service->findPlayableRace(2);

        $this->assertTrue(Cache::tags(['blizzard', 'blizzard-api-response'])->has($service->cacheKey('findPlayableRace', 2)));
    }

    #[Test]
    public function find_playable_race_caches_different_ids_separately(): void
    {
        $callCount = 0;

        Http::fake([
            'eu.api.blizzard.com/*' => function () use (&$callCount) {
                $callCount++;

                return Http::response(['id' => 1, 'name' => 'Human']);
            },
        ]);

        $service = $this->makeService();
        $service->findPlayableRace(1);
        $service->findPlayableRace(2);

        $this->assertEquals(2, $callCount);
    }

    #[Test]
    public function find_playable_race_throws_on_api_error(): void
    {
        Http::fake([
            'eu.api.blizzard.com/*' => Http::response(['error' => 'Not Found'], 404),
        ]);

        $service = $this->makeService();

        $this->expectException(RequestException::class);

        $service->findPlayableRace(999);
    }

    #[Test]
    public function find_playable_race_throws_invalid_race_exception_on_blizzard_404(): void
    {
        Http::fake([
            'eu.api.blizzard.com/*' => Http::response([
                'code' => 404,
                'type' => 'BLZWEBAPI00000404',
                'detail' => 'Not Found',
            ], 404),
        ]);

        $service = $this->makeService();

        $this->expectException(InvalidRaceException::class);
        $this->expectExceptionCode(404);

        $service->findPlayableRace(999);
    }

    // ==================== Items ====================

    #[Test]
    public function find_item_returns_item_data(): void
    {
        Http::fake([
            'eu.api.blizzard.com/*' => Http::response([
                'id' => 19019,
                'name' => 'Thunderfury, Blessed Blade of the Windseeker',
                'quality' => ['type' => 'LEGENDARY'],
            ]),
        ]);

        $service = $this->makeService();
        $item = $service->findItem(19019);

        $this->assertIsArray($item);
        $this->assertEquals(19019, $item['id']);
        $this->assertEquals('Thunderfury, Blessed Blade of the Windseeker', $item['name']);
    }

    #[Test]
    public function find_item_caches_result(): void
    {
        $callCount = 0;

        Http::fake([
            'eu.api.blizzard.com/*' => function () use (&$callCount) {
                $callCount++;

                return Http::response(['id' => 19019, 'name' => 'Thunderfury']);
            },
        ]);

        $service = $this->makeService();
        $service->findItem(19019);
        $service->findItem(19019);
        $service->findItem(19019);

        $this->assertEquals(1, $callCount);
    }

    #[Test]
    public function find_item_uses_standardised_cache_key(): void
    {
        Http::fake([
            'eu.api.blizzard.com/*' => Http::response(['id' => 19019]),
        ]);

        $service = $this->makeService();
        $service->findItem(19019);

        $this->assertTrue(Cache::tags(['blizzard', 'blizzard-api-response'])->has($service->cacheKey('findItem', 19019)));
    }

    #[Test]
    public function find_item_sends_static_namespace_header(): void
    {
        Http::fake([
            'eu.api.blizzard.com/*' => Http::response(['id' => 19019]),
        ]);

        $service = $this->makeService();
        $service->findItem(19019);

        Http::assertSent(function ($request) {
            if (str_contains($request->url(), 'api.blizzard.com')) {
                return $request->header('Battlenet-Namespace')[0] === 'static-classicann-eu';
            }

            return true;
        });
    }

    #[Test]
    public function find_item_builds_correct_endpoint(): void
    {
        Http::fake([
            'eu.api.blizzard.com/*' => Http::response(['id' => 19019]),
        ]);

        $service = $this->makeService();
        $service->findItem(19019);

        Http::assertSent(function ($request) {
            if (str_contains($request->url(), 'api.blizzard.com')) {
                return str_contains($request->url(), '/data/wow/item/19019');
            }

            return true;
        });
    }

    #[Test]
    public function find_item_throws_on_api_error(): void
    {
        Http::fake([
            'eu.api.blizzard.com/*' => Http::response(['error' => 'Not Found'], 404),
        ]);

        $service = $this->makeService();

        $this->expectException(RequestException::class);

        $service->findItem(99999999);
    }

    #[Test]
    public function find_item_caches_different_ids_separately(): void
    {
        $callCount = 0;

        Http::fake([
            'eu.api.blizzard.com/*' => function () use (&$callCount) {
                $callCount++;

                return Http::response(['id' => 1]);
            },
        ]);

        $service = $this->makeService();
        $service->findItem(19019);
        $service->findItem(28453);

        $this->assertEquals(2, $callCount);
    }

    #[Test]
    public function get_item_media_returns_media_data(): void
    {
        Http::fake([
            'eu.api.blizzard.com/*' => Http::response([
                'assets' => [
                    ['key' => 'icon', 'value' => 'https://render.worldofwarcraft.com/item/icon.jpg', 'file_data_id' => 123],
                ],
            ]),
        ]);

        $service = $this->makeService();
        $media = $service->getItemMedia(19019);

        $this->assertIsArray($media);
        $this->assertArrayHasKey('assets', $media);
        $this->assertEquals('icon', $media['assets'][0]['key']);
    }

    #[Test]
    public function get_item_media_calls_media_endpoint_directly(): void
    {
        Http::fake([
            'eu.api.blizzard.com/*' => Http::response(['assets' => []]),
        ]);

        $service = $this->makeService();
        $service->getItemMedia(19019);

        Http::assertSent(function ($request) {
            if (str_contains($request->url(), 'api.blizzard.com')) {
                return str_contains($request->url(), '/data/wow/media/item/19019');
            }

            return true;
        });
    }

    #[Test]
    public function get_item_media_sends_media_namespace_header(): void
    {
        Http::fake([
            'eu.api.blizzard.com/*' => Http::response(['assets' => []]),
        ]);

        $service = $this->makeService();
        $service->getItemMedia(19019);

        Http::assertSent(function ($request) {
            if (str_contains($request->url(), 'api.blizzard.com')) {
                return $request->header('Battlenet-Namespace')[0] === 'static-eu';
            }

            return true;
        });
    }

    #[Test]
    public function get_item_media_caches_result(): void
    {
        $callCount = 0;

        Http::fake([
            'eu.api.blizzard.com/*' => function () use (&$callCount) {
                $callCount++;

                return Http::response(['assets' => []]);
            },
        ]);

        $service = $this->makeService();
        $service->getItemMedia(19019);
        $service->getItemMedia(19019);

        $this->assertEquals(1, $callCount);
    }

    #[Test]
    public function get_item_media_uses_standardised_cache_key(): void
    {
        Http::fake([
            'eu.api.blizzard.com/*' => Http::response(['assets' => []]),
        ]);

        $service = $this->makeService();
        $service->getItemMedia(19019);

        $this->assertTrue(Cache::tags(['blizzard', 'blizzard-api-response'])->has($service->cacheKey('getItemMedia', 19019)));
    }

    #[Test]
    public function search_items_returns_results_with_pagination(): void
    {
        Http::fake([
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

        $service = $this->makeService();
        $results = $service->searchItems(['name' => 'Thunderfury']);

        $this->assertArrayHasKey('page', $results);
        $this->assertArrayHasKey('pageCount', $results);
        $this->assertArrayHasKey('results', $results);
        $this->assertEquals(1, $results['page']);
        $this->assertEquals(5, $results['pageCount']);
    }

    #[Test]
    public function search_items_maps_name_parameter(): void
    {
        Http::fake([
            'eu.api.blizzard.com/*' => Http::response(['results' => []]),
        ]);

        $service = $this->makeService();
        $service->searchItems(['name' => 'Thunderfury']);

        Http::assertSent(function ($request) {
            if (str_contains($request->url(), 'api.blizzard.com')) {
                $query = parse_url($request->url(), PHP_URL_QUERY);
                parse_str($query, $params);

                return isset($params['name.en_GB']) && $params['name.en_GB'] === 'Thunderfury';
            }

            return true;
        });
    }

    #[Test]
    public function search_items_maps_page_parameter(): void
    {
        Http::fake([
            'eu.api.blizzard.com/*' => Http::response(['results' => []]),
        ]);

        $service = $this->makeService();
        $service->searchItems(['page' => 3]);

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
    public function search_items_maps_page_size_parameter(): void
    {
        Http::fake([
            'eu.api.blizzard.com/*' => Http::response(['results' => []]),
        ]);

        $service = $this->makeService();
        $service->searchItems(['pageSize' => 50]);

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
    public function search_items_caps_page_size_at_maximum(): void
    {
        Http::fake([
            'eu.api.blizzard.com/*' => Http::response(['results' => []]),
        ]);

        $service = $this->makeService();
        $service->searchItems(['pageSize' => 5000]);

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
    public function search_items_accepts_orderby_parameter(): void
    {
        Http::fake([
            'eu.api.blizzard.com/*' => Http::response(['results' => []]),
        ]);

        $service = $this->makeService();
        $service->searchItems(['orderby' => 'name']);

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
    public function search_items_sends_static_namespace_header(): void
    {
        Http::fake([
            'eu.api.blizzard.com/*' => Http::response(['results' => []]),
        ]);

        $service = $this->makeService();
        $service->searchItems(['name' => 'Thunderfury']);

        Http::assertSent(function ($request) {
            if (str_contains($request->url(), 'api.blizzard.com')) {
                return $request->header('Battlenet-Namespace')[0] === 'static-classicann-eu';
            }

            return true;
        });
    }

    #[Test]
    public function search_items_caches_results(): void
    {
        $callCount = 0;

        Http::fake([
            'eu.api.blizzard.com/*' => function () use (&$callCount) {
                $callCount++;

                return Http::response(['results' => []]);
            },
        ]);

        $service = $this->makeService();
        $service->searchItems(['name' => 'Thunderfury']);
        $service->searchItems(['name' => 'Thunderfury']);

        $this->assertEquals(1, $callCount);
    }

    #[Test]
    public function search_items_cache_varies_by_parameters(): void
    {
        $callCount = 0;

        Http::fake([
            'eu.api.blizzard.com/*' => function () use (&$callCount) {
                $callCount++;

                return Http::response(['results' => []]);
            },
        ]);

        $service = $this->makeService();
        $service->searchItems(['name' => 'Thunderfury']);
        $service->searchItems(['name' => 'Ashkandi']);

        $this->assertEquals(2, $callCount);
    }

    // ==================== Playable Classes ====================

    #[Test]
    public function get_playable_classes_returns_class_data(): void
    {
        Http::fake([
            'eu.api.blizzard.com/*' => Http::response([
                'classes' => [
                    ['id' => 1, 'name' => 'Warrior'],
                    ['id' => 2, 'name' => 'Paladin'],
                ],
            ]),
        ]);

        $service = $this->makeService();
        $result = $service->getPlayableClasses();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('classes', $result);
        $this->assertCount(2, $result['classes']);
    }

    #[Test]
    public function get_playable_classes_builds_correct_endpoint(): void
    {
        Http::fake([
            'eu.api.blizzard.com/*' => Http::response(['classes' => []]),
        ]);

        $service = $this->makeService();
        $service->getPlayableClasses();

        Http::assertSent(function ($request) {
            if (str_contains($request->url(), 'api.blizzard.com')) {
                return str_contains($request->url(), '/data/wow/playable-class/index');
            }

            return true;
        });
    }

    #[Test]
    public function get_playable_classes_sends_static_namespace_header(): void
    {
        Http::fake([
            'eu.api.blizzard.com/*' => Http::response(['classes' => []]),
        ]);

        $service = $this->makeService();
        $service->getPlayableClasses();

        Http::assertSent(function ($request) {
            if (str_contains($request->url(), 'api.blizzard.com')) {
                return $request->header('Battlenet-Namespace')[0] === 'static-classicann-eu';
            }

            return true;
        });
    }

    #[Test]
    public function get_playable_classes_caches_result(): void
    {
        $callCount = 0;

        Http::fake([
            'eu.api.blizzard.com/*' => function () use (&$callCount) {
                $callCount++;

                return Http::response(['classes' => []]);
            },
        ]);

        $service = $this->makeService();
        $service->getPlayableClasses();
        $service->getPlayableClasses();
        $service->getPlayableClasses();

        $this->assertEquals(1, $callCount);
    }

    #[Test]
    public function get_playable_classes_uses_standardised_cache_key(): void
    {
        Http::fake([
            'eu.api.blizzard.com/*' => Http::response(['classes' => []]),
        ]);

        $service = $this->makeService();
        $service->getPlayableClasses();

        $this->assertTrue(Cache::tags(['blizzard', 'blizzard-api-response'])->has($service->cacheKey('getPlayableClasses')));
    }

    #[Test]
    public function get_playable_classes_throws_on_api_error(): void
    {
        Http::fake([
            'eu.api.blizzard.com/*' => Http::response(['error' => 'Not Found'], 404),
        ]);

        $service = $this->makeService();

        $this->expectException(RequestException::class);

        $service->getPlayableClasses();
    }

    #[Test]
    public function find_playable_class_returns_class_data(): void
    {
        Http::fake([
            'eu.api.blizzard.com/*' => Http::response([
                'id' => 1,
                'name' => 'Warrior',
                'power_type' => ['name' => 'Rage'],
            ]),
        ]);

        $service = $this->makeService();
        $result = $service->findPlayableClass(1);

        $this->assertIsArray($result);
        $this->assertEquals(1, $result['id']);
        $this->assertEquals('Warrior', $result['name']);
    }

    #[Test]
    public function find_playable_class_builds_correct_endpoint(): void
    {
        Http::fake([
            'eu.api.blizzard.com/*' => Http::response(['id' => 1, 'name' => 'Warrior']),
        ]);

        $service = $this->makeService();
        $service->findPlayableClass(1);

        Http::assertSent(function ($request) {
            if (str_contains($request->url(), 'api.blizzard.com')) {
                return str_contains($request->url(), '/data/wow/playable-class/1');
            }

            return true;
        });
    }

    #[Test]
    public function find_playable_class_sends_static_namespace_header(): void
    {
        Http::fake([
            'eu.api.blizzard.com/*' => Http::response(['id' => 1, 'name' => 'Warrior']),
        ]);

        $service = $this->makeService();
        $service->findPlayableClass(1);

        Http::assertSent(function ($request) {
            if (str_contains($request->url(), 'api.blizzard.com')) {
                return $request->header('Battlenet-Namespace')[0] === 'static-classicann-eu';
            }

            return true;
        });
    }

    #[Test]
    public function find_playable_class_caches_result(): void
    {
        $callCount = 0;

        Http::fake([
            'eu.api.blizzard.com/*' => function () use (&$callCount) {
                $callCount++;

                return Http::response(['id' => 1, 'name' => 'Warrior']);
            },
        ]);

        $service = $this->makeService();
        $service->findPlayableClass(1);
        $service->findPlayableClass(1);
        $service->findPlayableClass(1);

        $this->assertEquals(1, $callCount);
    }

    #[Test]
    public function find_playable_class_uses_standardised_cache_key(): void
    {
        Http::fake([
            'eu.api.blizzard.com/*' => Http::response(['id' => 1, 'name' => 'Warrior']),
        ]);

        $service = $this->makeService();
        $service->findPlayableClass(1);

        $this->assertTrue(Cache::tags(['blizzard', 'blizzard-api-response'])->has($service->cacheKey('findPlayableClass', 1)));
    }

    #[Test]
    public function find_playable_class_throws_on_api_error(): void
    {
        Http::fake([
            'eu.api.blizzard.com/*' => Http::response(['error' => 'Not Found'], 404),
        ]);

        $service = $this->makeService();

        $this->expectException(RequestException::class);

        $service->findPlayableClass(999);
    }

    #[Test]
    public function find_playable_class_throws_invalid_class_exception_on_blizzard_404(): void
    {
        Http::fake([
            'eu.api.blizzard.com/*' => Http::response([
                'code' => 404,
                'type' => 'BLZWEBAPI00000404',
                'detail' => 'Not Found',
            ], 404),
        ]);

        $service = $this->makeService();

        $this->expectException(InvalidClassException::class);
        $this->expectExceptionCode(404);

        $service->findPlayableClass(999);
    }

    #[Test]
    public function find_playable_class_caches_different_ids_separately(): void
    {
        $callCount = 0;

        Http::fake([
            'eu.api.blizzard.com/*' => function () use (&$callCount) {
                $callCount++;

                return Http::response(['id' => 1, 'name' => 'Warrior']);
            },
        ]);

        $service = $this->makeService();
        $service->findPlayableClass(1);
        $service->findPlayableClass(2);

        $this->assertEquals(2, $callCount);
    }

    #[Test]
    public function get_playable_class_media_returns_media_data(): void
    {
        Http::fake([
            'eu.api.blizzard.com/*' => Http::response([
                'assets' => [
                    ['key' => 'icon', 'value' => 'https://render.worldofwarcraft.com/icons/warrior.jpg', 'file_data_id' => 123],
                ],
            ]),
        ]);

        $service = $this->makeService();
        $media = $service->getPlayableClassMedia(1);

        $this->assertIsArray($media);
        $this->assertArrayHasKey('assets', $media);
        $this->assertEquals('icon', $media['assets'][0]['key']);
    }

    #[Test]
    public function get_playable_class_media_calls_media_endpoint_directly(): void
    {
        Http::fake([
            'eu.api.blizzard.com/*' => Http::response(['assets' => []]),
        ]);

        $service = $this->makeService();
        $service->getPlayableClassMedia(1);

        Http::assertSent(function ($request) {
            if (str_contains($request->url(), 'api.blizzard.com')) {
                return str_contains($request->url(), '/data/wow/media/playable-class/1');
            }

            return true;
        });
    }

    #[Test]
    public function get_playable_class_media_sends_media_namespace_header(): void
    {
        Http::fake([
            'eu.api.blizzard.com/*' => Http::response(['assets' => []]),
        ]);

        $service = $this->makeService();
        $service->getPlayableClassMedia(1);

        Http::assertSent(function ($request) {
            if (str_contains($request->url(), 'api.blizzard.com')) {
                return $request->header('Battlenet-Namespace')[0] === 'static-eu';
            }

            return true;
        });
    }

    #[Test]
    public function get_playable_class_media_caches_result(): void
    {
        $callCount = 0;

        Http::fake([
            'eu.api.blizzard.com/*' => function () use (&$callCount) {
                $callCount++;

                return Http::response(['assets' => []]);
            },
        ]);

        $service = $this->makeService();
        $service->getPlayableClassMedia(1);
        $service->getPlayableClassMedia(1);

        $this->assertEquals(1, $callCount);
    }

    #[Test]
    public function get_playable_class_media_uses_standardised_cache_key(): void
    {
        Http::fake([
            'eu.api.blizzard.com/*' => Http::response(['assets' => []]),
        ]);

        $service = $this->makeService();
        $service->getPlayableClassMedia(1);

        $this->assertTrue(Cache::tags(['blizzard', 'blizzard-api-response'])->has($service->cacheKey('getPlayableClassMedia', 1)));
    }

    // ==================== Media ====================

    #[Test]
    public function find_media_returns_media_data(): void
    {
        Http::fake([
            'eu.api.blizzard.com/*' => Http::response([
                'assets' => [
                    ['key' => 'icon', 'value' => 'https://render.worldofwarcraft.com/icons/56/inv_sword.jpg'],
                ],
            ]),
        ]);

        $service = $this->makeService();
        $media = $service->findMedia('item', 19019);

        $this->assertIsArray($media);
        $this->assertArrayHasKey('assets', $media);
        $this->assertEquals('icon', $media['assets'][0]['key']);
    }

    #[Test]
    public function find_media_caches_result(): void
    {
        $callCount = 0;

        Http::fake([
            'eu.api.blizzard.com/*' => function () use (&$callCount) {
                $callCount++;

                return Http::response(['assets' => []]);
            },
        ]);

        $service = $this->makeService();

        $service->findMedia('item', 19019);
        $service->findMedia('item', 19019);
        $service->findMedia('item', 19019);

        $this->assertEquals(1, $callCount);
    }

    #[Test]
    public function find_media_uses_standardised_cache_key(): void
    {
        Http::fake([
            'eu.api.blizzard.com/*' => Http::response(['assets' => []]),
        ]);

        $service = $this->makeService();
        $service->findMedia('item', 19019);

        $this->assertTrue(Cache::tags(['blizzard', 'blizzard-api-response'])->has($service->cacheKey('findMedia', 'item', 19019)));
    }

    #[Test]
    public function find_media_sends_media_namespace_header(): void
    {
        Http::fake([
            'eu.api.blizzard.com/*' => Http::response(['assets' => []]),
        ]);

        $service = $this->makeService();
        $service->findMedia('item', 19019);

        Http::assertSent(function ($request) {
            if (str_contains($request->url(), 'api.blizzard.com')) {
                return $request->header('Battlenet-Namespace')[0] === 'static-eu';
            }

            return true;
        });
    }

    #[Test]
    public function find_media_builds_correct_endpoint(): void
    {
        Http::fake([
            'eu.api.blizzard.com/*' => Http::response(['assets' => []]),
        ]);

        $service = $this->makeService();
        $service->findMedia('item', 19019);

        Http::assertSent(function ($request) {
            if (str_contains($request->url(), 'api.blizzard.com')) {
                return str_contains($request->url(), '/data/wow/media/item/19019');
            }

            return true;
        });
    }

    #[Test]
    public function find_media_throws_exception_for_invalid_tag(): void
    {
        $service = $this->makeService();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid tag(s): invalid. Allowed tags are: item, spell, playable-class');

        $service->findMedia('invalid', 19019);
    }

    #[Test]
    public function find_media_accepts_item_tag(): void
    {
        Http::fake([
            'eu.api.blizzard.com/*' => Http::response(['assets' => []]),
        ]);

        $service = $this->makeService();
        $media = $service->findMedia('item', 19019);

        $this->assertIsArray($media);

        Http::assertSent(function ($request) {
            if (str_contains($request->url(), 'api.blizzard.com')) {
                return str_contains($request->url(), '/media/item/19019');
            }

            return true;
        });
    }

    #[Test]
    public function find_media_accepts_spell_tag(): void
    {
        Http::fake([
            'eu.api.blizzard.com/*' => Http::response(['assets' => []]),
        ]);

        $service = $this->makeService();
        $media = $service->findMedia('spell', 12345);

        $this->assertIsArray($media);

        Http::assertSent(function ($request) {
            if (str_contains($request->url(), 'api.blizzard.com')) {
                return str_contains($request->url(), '/media/spell/12345');
            }

            return true;
        });
    }

    #[Test]
    public function find_media_accepts_playable_class_tag(): void
    {
        Http::fake([
            'eu.api.blizzard.com/*' => Http::response(['assets' => []]),
        ]);

        $service = $this->makeService();
        $media = $service->findMedia('playable-class', 1);

        $this->assertIsArray($media);

        Http::assertSent(function ($request) {
            if (str_contains($request->url(), 'api.blizzard.com')) {
                return str_contains($request->url(), '/media/playable-class/1');
            }

            return true;
        });
    }

    #[Test]
    public function find_media_throws_on_api_error(): void
    {
        Http::fake([
            'eu.api.blizzard.com/*' => Http::response(['error' => 'Not Found'], 404),
        ]);

        $service = $this->makeService();

        $this->expectException(RequestException::class);

        $service->findMedia('item', 99999999);
    }

    #[Test]
    public function search_media_returns_results(): void
    {
        Http::fake([
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

        $service = $this->makeService();
        $results = $service->searchMedia(['tags' => ['item']]);

        $this->assertArrayHasKey('page', $results);
        $this->assertArrayHasKey('pageCount', $results);
        $this->assertArrayHasKey('results', $results);
        $this->assertEquals(1, $results['page']);
    }

    #[Test]
    public function search_media_requires_tags_parameter(): void
    {
        $service = $this->makeService();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The "tags" parameter is required for media search.');

        $service->searchMedia(['name' => 'test']);
    }

    #[Test]
    public function search_media_throws_for_invalid_tags(): void
    {
        $service = $this->makeService();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid tag(s): invalid. Allowed tags are: item, spell, playable-class');

        $service->searchMedia(['tags' => ['item', 'invalid']]);
    }

    #[Test]
    public function search_media_maps_tags_parameter(): void
    {
        Http::fake([
            'eu.api.blizzard.com/*' => Http::response(['results' => []]),
        ]);

        $service = $this->makeService();
        $service->searchMedia(['tags' => ['item', 'spell']]);

        Http::assertSent(function ($request) {
            if (str_contains($request->url(), 'api.blizzard.com')) {
                $query = parse_url($request->url(), PHP_URL_QUERY);
                parse_str($query, $params);

                return isset($params['_tags']) && $params['_tags'] === 'item,spell';
            }

            return true;
        });
    }

    #[Test]
    public function search_media_maps_item_id_parameter(): void
    {
        Http::fake([
            'eu.api.blizzard.com/*' => Http::response(['results' => []]),
        ]);

        $service = $this->makeService();
        $service->searchMedia(['tags' => ['item'], 'itemId' => 19019]);

        Http::assertSent(function ($request) {
            if (str_contains($request->url(), 'api.blizzard.com')) {
                $query = parse_url($request->url(), PHP_URL_QUERY);
                parse_str($query, $params);

                return isset($params['itemId']) && $params['itemId'] === '19019';
            }

            return true;
        });
    }

    #[Test]
    public function search_media_maps_name_parameter(): void
    {
        Http::fake([
            'eu.api.blizzard.com/*' => Http::response(['results' => []]),
        ]);

        $service = $this->makeService();
        $service->searchMedia(['tags' => ['item'], 'name' => 'Thunderfury']);

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
    public function search_media_maps_page_parameter(): void
    {
        Http::fake([
            'eu.api.blizzard.com/*' => Http::response(['results' => []]),
        ]);

        $service = $this->makeService();
        $service->searchMedia(['tags' => ['item'], 'page' => 3]);

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
    public function search_media_maps_page_size_parameter(): void
    {
        Http::fake([
            'eu.api.blizzard.com/*' => Http::response(['results' => []]),
        ]);

        $service = $this->makeService();
        $service->searchMedia(['tags' => ['item'], 'pageSize' => 50]);

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
    public function search_media_caps_page_size_at_maximum(): void
    {
        Http::fake([
            'eu.api.blizzard.com/*' => Http::response(['results' => []]),
        ]);

        $service = $this->makeService();
        $service->searchMedia(['tags' => ['item'], 'pageSize' => 5000]);

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
    public function search_media_caches_results(): void
    {
        $callCount = 0;

        Http::fake([
            'eu.api.blizzard.com/*' => function () use (&$callCount) {
                $callCount++;

                return Http::response(['results' => []]);
            },
        ]);

        $service = $this->makeService();

        $service->searchMedia(['tags' => ['item'], 'name' => 'Thunderfury']);
        $service->searchMedia(['tags' => ['item'], 'name' => 'Thunderfury']);

        $this->assertEquals(1, $callCount);
    }

    #[Test]
    public function search_media_cache_varies_by_parameters(): void
    {
        $callCount = 0;

        Http::fake([
            'eu.api.blizzard.com/*' => function () use (&$callCount) {
                $callCount++;

                return Http::response(['results' => []]);
            },
        ]);

        $service = $this->makeService();

        $service->searchMedia(['tags' => ['item'], 'name' => 'Thunderfury']);
        $service->searchMedia(['tags' => ['item'], 'name' => 'Ashkandi']);

        $this->assertEquals(2, $callCount);
    }

    #[Test]
    public function search_media_sends_media_namespace_header(): void
    {
        Http::fake([
            'eu.api.blizzard.com/*' => Http::response(['results' => []]),
        ]);

        $service = $this->makeService();
        $service->searchMedia(['tags' => ['item']]);

        Http::assertSent(function ($request) {
            if (str_contains($request->url(), 'api.blizzard.com')) {
                return $request->header('Battlenet-Namespace')[0] === 'static-eu';
            }

            return true;
        });
    }

    #[Test]
    public function validate_media_tags_throws_for_multiple_invalid_tags(): void
    {
        $service = $this->makeService();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid tag(s): foo, bar. Allowed tags are: item, spell, playable-class');

        $service->searchMedia(['tags' => ['foo', 'bar']]);
    }
}
