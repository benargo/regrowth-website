<?php

namespace Tests\Feature\Services\Blizzard;

use App\Events\GuildRosterFetched;
use App\Services\Blizzard\Client;
use App\Services\Blizzard\GuildService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
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

    public function test_roster_dispatches_guild_roster_fetched_event(): void
    {
        Event::fake([GuildRosterFetched::class]);

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

        Event::assertDispatched(GuildRosterFetched::class);
    }

    public function test_event_carries_full_roster_including_multiple_members(): void
    {
        Event::fake([GuildRosterFetched::class]);

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

        $service->roster();

        Event::assertDispatched(GuildRosterFetched::class, function (GuildRosterFetched $event) {
            return count($event->roster['members']) === 2
                && $event->roster['members'][0]['character']['id'] === 123
                && $event->roster['members'][1]['character']['id'] === 456;
        });
    }

    public function test_event_not_dispatched_on_cache_hit(): void
    {
        Event::fake([GuildRosterFetched::class]);

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

        $service->roster();  // API hit — event dispatched
        $service->roster();  // Cache hit — no event

        Event::assertDispatchedTimes(GuildRosterFetched::class, 1);
    }
}
