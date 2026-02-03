<?php

namespace Tests\Unit\Services\Blizzard;

use App\Services\Blizzard\CharacterService;
use App\Services\Blizzard\Client;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CharacterServiceTest extends TestCase
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

    public function test_get_profile_returns_character_data(): void
    {
        Http::fake([
            'eu.battle.net/oauth/token' => Http::response([
                'access_token' => 'test_token',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ]),
            'eu.api.blizzard.com/*' => Http::response([
                'id' => 12345,
                'name' => 'Testchar',
                'level' => 60,
                'character_class' => ['name' => 'Warrior'],
            ]),
        ]);

        $client = new Client('client_id', 'client_secret', namespace: 'profile-classicann-eu');
        $service = new CharacterService($client);

        $profile = $service->getProfile('Testchar', 'thunderstrike');

        $this->assertIsArray($profile);
        $this->assertEquals(12345, $profile['id']);
        $this->assertEquals('Testchar', $profile['name']);
        $this->assertEquals(60, $profile['level']);
    }

    public function test_get_profile_caches_result(): void
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

                return Http::response(['id' => 12345, 'name' => 'Testchar']);
            },
        ]);

        $client = new Client('client_id', 'client_secret', namespace: 'profile-classicann-eu');
        $service = new CharacterService($client);

        $service->getProfile('Testchar', 'thunderstrike');
        $service->getProfile('Testchar', 'thunderstrike');
        $service->getProfile('Testchar', 'thunderstrike');

        $this->assertEquals(1, $callCount);
    }

    public function test_get_profile_uses_namespace_in_cache_key(): void
    {
        Http::fake([
            'eu.battle.net/oauth/token' => Http::response([
                'access_token' => 'test_token',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ]),
            'eu.api.blizzard.com/*' => Http::response(['id' => 12345, 'name' => 'Testchar']),
        ]);

        $client = new Client('client_id', 'client_secret', namespace: 'profile-classicann-eu');
        $service = new CharacterService($client);

        $service->getProfile('Testchar', 'thunderstrike');

        $this->assertTrue(Cache::has('blizzard.character.profile.thunderstrike.testchar.profile-classicann-eu'));
    }

    public function test_get_profile_uses_default_realm_when_not_provided(): void
    {
        Http::fake([
            'eu.battle.net/oauth/token' => Http::response([
                'access_token' => 'test_token',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ]),
            'eu.api.blizzard.com/*' => Http::response(['id' => 12345, 'name' => 'Testchar']),
        ]);

        $client = new Client('client_id', 'client_secret', namespace: 'profile-classicann-eu');
        $service = new CharacterService($client);

        $service->getProfile('Testchar');

        Http::assertSent(function ($request) {
            if (str_contains($request->url(), 'api.blizzard.com')) {
                return str_contains($request->url(), '/thunderstrike/testchar');
            }

            return true;
        });
    }

    public function test_get_profile_uses_provided_realm(): void
    {
        Http::fake([
            'eu.battle.net/oauth/token' => Http::response([
                'access_token' => 'test_token',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ]),
            'eu.api.blizzard.com/*' => Http::response(['id' => 12345, 'name' => 'Testchar']),
        ]);

        $client = new Client('client_id', 'client_secret', namespace: 'profile-classicann-eu');
        $service = new CharacterService($client);

        $service->getProfile('Testchar', 'Wild Growth');

        Http::assertSent(function ($request) {
            if (str_contains($request->url(), 'api.blizzard.com')) {
                return str_contains($request->url(), '/wild-growth/testchar');
            }

            return true;
        });
    }

    public function test_get_profile_converts_name_to_lowercase(): void
    {
        Http::fake([
            'eu.battle.net/oauth/token' => Http::response([
                'access_token' => 'test_token',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ]),
            'eu.api.blizzard.com/*' => Http::response(['id' => 12345, 'name' => 'TestChar']),
        ]);

        $client = new Client('client_id', 'client_secret', namespace: 'profile-classicann-eu');
        $service = new CharacterService($client);

        $service->getProfile('TestChar', 'thunderstrike');

        Http::assertSent(function ($request) {
            if (str_contains($request->url(), 'api.blizzard.com')) {
                return str_contains($request->url(), '/testchar');
            }

            return true;
        });
    }

    public function test_get_profile_throws_exception_for_invalid_character(): void
    {
        Http::fake([
            'eu.battle.net/oauth/token' => Http::response([
                'access_token' => 'test_token',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ]),
            'eu.api.blizzard.com/*' => Http::response(['error' => 'Not Found'], 404),
        ]);

        $client = new Client('client_id', 'client_secret', namespace: 'profile-classicann-eu');
        $service = new CharacterService($client);

        $this->expectException(RequestException::class);

        $service->getProfile('NonExistentChar', 'thunderstrike');
    }

    public function test_fresh_get_profile_bypasses_cache(): void
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

                return Http::response(['id' => 12345, 'name' => 'Testchar']);
            },
        ]);

        $client = new Client('client_id', 'client_secret', namespace: 'profile-classicann-eu');
        $service = new CharacterService($client);

        $service->getProfile('Testchar', 'thunderstrike');
        $service->fresh()->getProfile('Testchar', 'thunderstrike');

        $this->assertEquals(2, $callCount);
    }

    public function test_get_profile_status_returns_status_data(): void
    {
        Http::fake([
            'eu.battle.net/oauth/token' => Http::response([
                'access_token' => 'test_token',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ]),
            'eu.api.blizzard.com/*' => Http::response([
                'id' => 12345,
                'is_valid' => true,
            ]),
        ]);

        $client = new Client('client_id', 'client_secret', namespace: 'profile-classicann-eu');
        $service = new CharacterService($client);

        $status = $service->getStatus('Testchar', 'thunderstrike');

        $this->assertIsArray($status);
        $this->assertEquals(12345, $status['id']);
        $this->assertTrue($status['is_valid']);
    }

    public function test_get_profile_status_caches_result(): void
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

                return Http::response(['id' => 12345, 'is_valid' => true]);
            },
        ]);

        $client = new Client('client_id', 'client_secret', namespace: 'profile-classicann-eu');
        $service = new CharacterService($client);

        $service->getStatus('Testchar', 'thunderstrike');
        $service->getStatus('Testchar', 'thunderstrike');

        $this->assertEquals(1, $callCount);
    }

    public function test_get_profile_status_uses_namespace_in_cache_key(): void
    {
        Http::fake([
            'eu.battle.net/oauth/token' => Http::response([
                'access_token' => 'test_token',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ]),
            'eu.api.blizzard.com/*' => Http::response(['id' => 12345, 'is_valid' => true]),
        ]);

        $client = new Client('client_id', 'client_secret', namespace: 'profile-classicann-eu');
        $service = new CharacterService($client);

        $service->getStatus('Testchar', 'thunderstrike');

        $this->assertTrue(Cache::has('blizzard.character.status.thunderstrike.testchar.profile-classicann-eu'));
    }

    public function test_get_profile_status_builds_correct_endpoint(): void
    {
        Http::fake([
            'eu.battle.net/oauth/token' => Http::response([
                'access_token' => 'test_token',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ]),
            'eu.api.blizzard.com/*' => Http::response(['id' => 12345, 'is_valid' => true]),
        ]);

        $client = new Client('client_id', 'client_secret', namespace: 'profile-classicann-eu');
        $service = new CharacterService($client);

        $service->getStatus('Testchar', 'thunderstrike');

        Http::assertSent(function ($request) {
            if (str_contains($request->url(), 'api.blizzard.com')) {
                return str_contains($request->url(), '/thunderstrike/testchar/status');
            }

            return true;
        });
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

                return Http::response(['id' => 12345, 'name' => 'Testchar']);
            },
        ]);

        $client = new Client('client_id', 'client_secret', namespace: 'profile-classicann-eu');
        $service = new CharacterService($client);

        $service->getProfile('Testchar', 'thunderstrike');
        $service->withNamespace('profile-classic1x-eu')->getProfile('Testchar', 'thunderstrike');

        $this->assertEquals(2, $callCount);
        $this->assertTrue(Cache::has('blizzard.character.profile.thunderstrike.testchar.profile-classic1x-eu'));
    }
}
