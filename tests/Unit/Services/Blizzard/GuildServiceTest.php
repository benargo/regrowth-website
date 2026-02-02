<?php

namespace Tests\Unit\Services\Blizzard;

use App\Models\Character;
use App\Models\GuildRank;
use App\Services\Blizzard\Client;
use App\Services\Blizzard\Data\GuildMember;
use App\Services\Blizzard\GuildService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GuildServiceTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_constructor_sets_profile_classicann_namespace(): void
    {
        $client = new Client('client_id', 'client_secret');
        new GuildService($client);

        $this->assertEquals('profile-classicann-eu', $client->getNamespace());
    }

    public function test_roster_returns_raw_api_response(): void
    {
        $expectedResponse = [
            'members' => [
                [
                    'character' => [
                        'id' => 123,
                        'name' => 'TestChar',
                        'level' => 60,
                        'realm' => ['id' => 1, 'name' => 'Thunderstrike', 'slug' => 'thunderstrike'],
                        'playable_class' => ['id' => 11],
                        'playable_race' => ['id' => 4],
                        'faction' => ['type' => 'ALLIANCE'],
                    ],
                    'rank' => 0,
                ],
                [
                    'character' => [
                        'id' => 456,
                        'name' => 'AnotherChar',
                        'level' => 55,
                        'realm' => ['id' => 1, 'name' => 'Thunderstrike', 'slug' => 'thunderstrike'],
                        'playable_class' => ['id' => 2],
                        'playable_race' => ['id' => 1],
                        'faction' => ['type' => 'ALLIANCE'],
                    ],
                    'rank' => 3,
                ],
            ],
        ];

        Http::fake([
            'eu.battle.net/oauth/token' => Http::response([
                'access_token' => 'test_token',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ]),
            'eu.api.blizzard.com/*' => Http::response($expectedResponse),
        ]);

        $client = new Client('client_id', 'client_secret');
        $service = new GuildService($client);

        $roster = $service->roster();

        $this->assertIsArray($roster);
        $this->assertArrayHasKey('members', $roster);
        $this->assertCount(2, $roster['members']);
        $this->assertEquals(0, $roster['members'][0]['rank']);
        $this->assertEquals('TestChar', $roster['members'][0]['character']['name']);
    }

    public function test_roster_uses_default_realm_and_name_slugs(): void
    {
        Http::fake([
            'eu.battle.net/oauth/token' => Http::response([
                'access_token' => 'test_token',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ]),
            'eu.api.blizzard.com/*' => Http::response(['members' => []]),
        ]);

        $client = new Client('client_id', 'client_secret');
        $service = new GuildService($client);

        $service->roster();

        Http::assertSent(function ($request) {
            if (str_contains($request->url(), 'api.blizzard.com')) {
                return str_contains($request->url(), '/guild/thunderstrike/regrowth/roster');
            }

            return true;
        });
    }

    public function test_roster_accepts_custom_realm_slug(): void
    {
        Http::fake([
            'eu.battle.net/oauth/token' => Http::response([
                'access_token' => 'test_token',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ]),
            'eu.api.blizzard.com/*' => Http::response(['members' => []]),
        ]);

        $client = new Client('client_id', 'client_secret');
        $service = new GuildService($client);

        $service->roster('custom-realm');

        Http::assertSent(function ($request) {
            if (str_contains($request->url(), 'api.blizzard.com')) {
                return str_contains($request->url(), '/guild/custom-realm/regrowth/roster');
            }

            return true;
        });
    }

    public function test_roster_accepts_custom_name_slug(): void
    {
        Http::fake([
            'eu.battle.net/oauth/token' => Http::response([
                'access_token' => 'test_token',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ]),
            'eu.api.blizzard.com/*' => Http::response(['members' => []]),
        ]);

        $client = new Client('client_id', 'client_secret');
        $service = new GuildService($client);

        $service->roster('thunderstrike', 'another-guild');

        Http::assertSent(function ($request) {
            if (str_contains($request->url(), 'api.blizzard.com')) {
                return str_contains($request->url(), '/guild/thunderstrike/another-guild/roster');
            }

            return true;
        });
    }

    public function test_roster_accepts_custom_realm_and_name_slugs(): void
    {
        Http::fake([
            'eu.battle.net/oauth/token' => Http::response([
                'access_token' => 'test_token',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ]),
            'eu.api.blizzard.com/*' => Http::response(['members' => []]),
        ]);

        $client = new Client('client_id', 'client_secret');
        $service = new GuildService($client);

        $service->roster('other-realm', 'other-guild');

        Http::assertSent(function ($request) {
            if (str_contains($request->url(), 'api.blizzard.com')) {
                return str_contains($request->url(), '/guild/other-realm/other-guild/roster');
            }

            return true;
        });
    }

    public function test_roster_caches_result(): void
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

                return Http::response(['members' => []]);
            },
        ]);

        $client = new Client('client_id', 'client_secret');
        $service = new GuildService($client);

        $service->roster();
        $service->roster();
        $service->roster();

        $this->assertEquals(1, $callCount);
    }

    public function test_roster_uses_correct_cache_key(): void
    {
        Http::fake([
            'eu.battle.net/oauth/token' => Http::response([
                'access_token' => 'test_token',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ]),
            'eu.api.blizzard.com/*' => Http::response(['members' => []]),
        ]);

        $client = new Client('client_id', 'client_secret');
        $service = new GuildService($client);

        $service->roster();

        $this->assertTrue(Cache::has('blizzard.guild.roster.thunderstrike.regrowth.profile-classicann-eu'));
    }

    public function test_roster_cache_key_varies_by_realm_slug(): void
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

                return Http::response(['members' => []]);
            },
        ]);

        $client = new Client('client_id', 'client_secret');
        $service = new GuildService($client);

        $service->roster('thunderstrike', 'regrowth');
        $service->roster('other-realm', 'regrowth');

        $this->assertEquals(2, $callCount);
        $this->assertTrue(Cache::has('blizzard.guild.roster.thunderstrike.regrowth.profile-classicann-eu'));
        $this->assertTrue(Cache::has('blizzard.guild.roster.other-realm.regrowth.profile-classicann-eu'));
    }

    public function test_roster_cache_key_varies_by_name_slug(): void
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

                return Http::response(['members' => []]);
            },
        ]);

        $client = new Client('client_id', 'client_secret');
        $service = new GuildService($client);

        $service->roster('thunderstrike', 'regrowth');
        $service->roster('thunderstrike', 'other-guild');

        $this->assertEquals(2, $callCount);
        $this->assertTrue(Cache::has('blizzard.guild.roster.thunderstrike.regrowth.profile-classicann-eu'));
        $this->assertTrue(Cache::has('blizzard.guild.roster.thunderstrike.other-guild.profile-classicann-eu'));
    }

    public function test_roster_returns_empty_members_array_when_no_members(): void
    {
        Http::fake([
            'eu.battle.net/oauth/token' => Http::response([
                'access_token' => 'test_token',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ]),
            'eu.api.blizzard.com/*' => Http::response(['members' => []]),
        ]);

        $client = new Client('client_id', 'client_secret');
        $service = new GuildService($client);

        $roster = $service->roster();

        $this->assertIsArray($roster);
        $this->assertArrayHasKey('members', $roster);
        $this->assertEmpty($roster['members']);
    }

    public function test_roster_returns_response_without_members_key(): void
    {
        Http::fake([
            'eu.battle.net/oauth/token' => Http::response([
                'access_token' => 'test_token',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ]),
            'eu.api.blizzard.com/*' => Http::response(['guild' => ['name' => 'Regrowth']]),
        ]);

        $client = new Client('client_id', 'client_secret');
        $service = new GuildService($client);

        $roster = $service->roster();

        $this->assertIsArray($roster);
        $this->assertArrayNotHasKey('members', $roster);
        $this->assertArrayHasKey('guild', $roster);
    }

    public function test_roster_throws_exception_for_invalid_guild(): void
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
        $service = new GuildService($client);

        $this->expectException(RequestException::class);

        $service->roster('nonexistent-realm', 'nonexistent-guild');
    }

    public function test_members_returns_collection_of_guild_member_objects(): void
    {
        Http::fake([
            'eu.battle.net/oauth/token' => Http::response([
                'access_token' => 'test_token',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ]),
            'eu.api.blizzard.com/*' => Http::response([
                'members' => [
                    [
                        'character' => [
                            'id' => 123,
                            'name' => 'TestChar',
                            'level' => 60,
                            'realm' => ['id' => 1, 'name' => 'Thunderstrike', 'slug' => 'thunderstrike'],
                            'playable_class' => ['id' => 11],
                            'playable_race' => ['id' => 4],
                            'faction' => ['type' => 'ALLIANCE'],
                        ],
                        'rank' => 0,
                    ],
                    [
                        'character' => [
                            'id' => 456,
                            'name' => 'AnotherChar',
                            'level' => 55,
                            'realm' => ['id' => 1, 'name' => 'Thunderstrike', 'slug' => 'thunderstrike'],
                            'playable_class' => ['id' => 2],
                            'playable_race' => ['id' => 1],
                            'faction' => ['type' => 'ALLIANCE'],
                        ],
                        'rank' => 3,
                    ],
                ],
            ]),
        ]);

        $client = new Client('client_id', 'client_secret');
        $service = new GuildService($client);

        $members = $service->members();

        $this->assertInstanceOf(Collection::class, $members);
        $this->assertCount(2, $members);
        $this->assertInstanceOf(GuildMember::class, $members->first());
        $this->assertEquals(0, $members->first()->rank);
        $this->assertEquals('TestChar', $members->first()->character['name']);
    }

    public function test_members_maps_to_guild_member_objects(): void
    {
        Http::fake([
            'eu.battle.net/oauth/token' => Http::response([
                'access_token' => 'test_token',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ]),
            'eu.api.blizzard.com/*' => Http::response([
                'members' => [
                    [
                        'character' => [
                            'id' => 789,
                            'name' => 'Healbot',
                            'level' => 60,
                            'realm' => ['id' => 1, 'name' => 'Thunderstrike', 'slug' => 'thunderstrike'],
                            'playable_class' => ['id' => 11],
                            'playable_race' => ['id' => 4],
                            'faction' => ['type' => 'ALLIANCE'],
                        ],
                        'rank' => 5,
                    ],
                ],
            ]),
        ]);

        $client = new Client('client_id', 'client_secret');
        $service = new GuildService($client);

        $members = $service->members();
        $member = $members->first();

        $this->assertInstanceOf(GuildMember::class, $member);
        $this->assertEquals(5, $member->rank);
        $this->assertEquals(789, $member->character['id']);
        $this->assertEquals('Healbot', $member->character['name']);
        $this->assertEquals(60, $member->character['level']);
    }

    public function test_members_returns_empty_collection_when_no_members(): void
    {
        Http::fake([
            'eu.battle.net/oauth/token' => Http::response([
                'access_token' => 'test_token',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ]),
            'eu.api.blizzard.com/*' => Http::response(['members' => []]),
        ]);

        $client = new Client('client_id', 'client_secret');
        $service = new GuildService($client);

        $members = $service->members();

        $this->assertInstanceOf(Collection::class, $members);
        $this->assertCount(0, $members);
    }

    public function test_members_handles_missing_members_key(): void
    {
        Http::fake([
            'eu.battle.net/oauth/token' => Http::response([
                'access_token' => 'test_token',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ]),
            'eu.api.blizzard.com/*' => Http::response(['guild' => ['name' => 'Regrowth']]),
        ]);

        $client = new Client('client_id', 'client_secret');
        $service = new GuildService($client);

        $members = $service->members();

        $this->assertInstanceOf(Collection::class, $members);
        $this->assertCount(0, $members);
    }

    public function test_members_accepts_custom_realm_and_name_slugs(): void
    {
        Http::fake([
            'eu.battle.net/oauth/token' => Http::response([
                'access_token' => 'test_token',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ]),
            'eu.api.blizzard.com/*' => Http::response(['members' => []]),
        ]);

        $client = new Client('client_id', 'client_secret');
        $service = new GuildService($client);

        $service->members('other-realm', 'other-guild');

        Http::assertSent(function ($request) {
            if (str_contains($request->url(), 'api.blizzard.com')) {
                return str_contains($request->url(), '/guild/other-realm/other-guild/roster');
            }

            return true;
        });
    }

    public function test_roster_creates_character_when_guild_rank_exists(): void
    {
        $guildRank = GuildRank::factory()->create(['position' => 0]);

        Http::fake([
            'eu.battle.net/oauth/token' => Http::response([
                'access_token' => 'test_token',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ]),
            'eu.api.blizzard.com/*' => Http::response([
                'members' => [
                    [
                        'character' => [
                            'id' => 123,
                            'name' => 'TestChar',
                            'level' => 60,
                            'realm' => ['id' => 1, 'name' => 'Thunderstrike', 'slug' => 'thunderstrike'],
                            'playable_class' => ['id' => 11],
                            'playable_race' => ['id' => 4],
                            'faction' => ['type' => 'ALLIANCE'],
                        ],
                        'rank' => 0,
                    ],
                ],
            ]),
        ]);

        $client = new Client('client_id', 'client_secret');
        $service = new GuildService($client);

        $service->roster();

        $this->assertDatabaseHas('characters', [
            'id' => 123,
            'name' => 'TestChar',
            'rank_id' => $guildRank->id,
        ]);
    }

    public function test_roster_updates_existing_character(): void
    {
        $guildRank = GuildRank::factory()->create(['position' => 0]);
        $character = Character::factory()->create([
            'id' => 123,
            'name' => 'OldName',
            'rank_id' => null,
        ]);

        Http::fake([
            'eu.battle.net/oauth/token' => Http::response([
                'access_token' => 'test_token',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ]),
            'eu.api.blizzard.com/*' => Http::response([
                'members' => [
                    [
                        'character' => [
                            'id' => 123,
                            'name' => 'NewName',
                            'level' => 60,
                            'realm' => ['id' => 1, 'name' => 'Thunderstrike', 'slug' => 'thunderstrike'],
                            'playable_class' => ['id' => 11],
                            'playable_race' => ['id' => 4],
                            'faction' => ['type' => 'ALLIANCE'],
                        ],
                        'rank' => 0,
                    ],
                ],
            ]),
        ]);

        $client = new Client('client_id', 'client_secret');
        $service = new GuildService($client);

        $service->roster();

        $this->assertDatabaseHas('characters', [
            'id' => 123,
            'name' => 'NewName',
            'rank_id' => $guildRank->id,
        ]);
        $this->assertDatabaseCount('characters', 1);
    }

    public function test_roster_does_not_create_character_when_guild_rank_not_found(): void
    {
        Http::fake([
            'eu.battle.net/oauth/token' => Http::response([
                'access_token' => 'test_token',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ]),
            'eu.api.blizzard.com/*' => Http::response([
                'members' => [
                    [
                        'character' => [
                            'id' => 123,
                            'name' => 'TestChar',
                            'level' => 60,
                            'realm' => ['id' => 1, 'name' => 'Thunderstrike', 'slug' => 'thunderstrike'],
                            'playable_class' => ['id' => 11],
                            'playable_race' => ['id' => 4],
                            'faction' => ['type' => 'ALLIANCE'],
                        ],
                        'rank' => 0,
                    ],
                ],
            ]),
        ]);

        $client = new Client('client_id', 'client_secret');
        $service = new GuildService($client);

        $service->roster();

        $this->assertDatabaseMissing('characters', ['id' => 123]);
    }

    public function test_roster_creates_multiple_characters(): void
    {
        $guildMaster = GuildRank::factory()->create(['position' => 0]);
        $officer = GuildRank::factory()->create(['position' => 3]);

        Http::fake([
            'eu.battle.net/oauth/token' => Http::response([
                'access_token' => 'test_token',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ]),
            'eu.api.blizzard.com/*' => Http::response([
                'members' => [
                    [
                        'character' => [
                            'id' => 123,
                            'name' => 'GuildMasterChar',
                            'level' => 60,
                            'realm' => ['id' => 1, 'name' => 'Thunderstrike', 'slug' => 'thunderstrike'],
                            'playable_class' => ['id' => 11],
                            'playable_race' => ['id' => 4],
                            'faction' => ['type' => 'ALLIANCE'],
                        ],
                        'rank' => 0,
                    ],
                    [
                        'character' => [
                            'id' => 456,
                            'name' => 'OfficerChar',
                            'level' => 55,
                            'realm' => ['id' => 1, 'name' => 'Thunderstrike', 'slug' => 'thunderstrike'],
                            'playable_class' => ['id' => 2],
                            'playable_race' => ['id' => 1],
                            'faction' => ['type' => 'ALLIANCE'],
                        ],
                        'rank' => 3,
                    ],
                ],
            ]),
        ]);

        $client = new Client('client_id', 'client_secret');
        $service = new GuildService($client);

        $service->roster();

        $this->assertDatabaseHas('characters', [
            'id' => 123,
            'name' => 'GuildMasterChar',
            'rank_id' => $guildMaster->id,
        ]);
        $this->assertDatabaseHas('characters', [
            'id' => 456,
            'name' => 'OfficerChar',
            'rank_id' => $officer->id,
        ]);
    }

    public function test_roster_updates_character_rank(): void
    {
        $oldRank = GuildRank::factory()->create(['position' => 5]);
        $newRank = GuildRank::factory()->create(['position' => 3]);
        $character = Character::factory()->create([
            'id' => 123,
            'name' => 'TestChar',
            'rank_id' => $oldRank->id,
        ]);

        Http::fake([
            'eu.battle.net/oauth/token' => Http::response([
                'access_token' => 'test_token',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ]),
            'eu.api.blizzard.com/*' => Http::response([
                'members' => [
                    [
                        'character' => [
                            'id' => 123,
                            'name' => 'TestChar',
                            'level' => 60,
                            'realm' => ['id' => 1, 'name' => 'Thunderstrike', 'slug' => 'thunderstrike'],
                            'playable_class' => ['id' => 11],
                            'playable_race' => ['id' => 4],
                            'faction' => ['type' => 'ALLIANCE'],
                        ],
                        'rank' => 3,
                    ],
                ],
            ]),
        ]);

        $client = new Client('client_id', 'client_secret');
        $service = new GuildService($client);

        $service->roster();

        $this->assertDatabaseHas('characters', [
            'id' => 123,
            'rank_id' => $newRank->id,
        ]);
    }
}
