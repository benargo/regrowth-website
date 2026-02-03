<?php

namespace Tests\Feature\Services\Blizzard;

use App\Models\Character;
use App\Models\GuildRank;
use App\Services\Blizzard\Client;
use App\Services\Blizzard\GuildService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GuildServiceCharacterUpdateTest extends TestCase
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

        $service->shouldUpdateCharacters()->roster();

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

        $service->shouldUpdateCharacters()->roster();

        $this->assertDatabaseHas('characters', [
            'id' => 123,
            'name' => 'NewName',
            'rank_id' => $guildRank->id,
        ]);
        $this->assertDatabaseCount('characters', 1);
    }

    public function test_roster_does_not_create_character_when_guild_rank_not_found(): void
    {
        $this->expectException(ModelNotFoundException::class);

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

        $service->shouldUpdateCharacters()->roster();
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
                            'level' => 70,
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
                            'level' => 60,
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

        $service->shouldUpdateCharacters()->roster();

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

        $service->shouldUpdateCharacters()->roster();

        $this->assertDatabaseHas('characters', [
            'id' => 123,
            'rank_id' => $newRank->id,
        ]);
    }
}
