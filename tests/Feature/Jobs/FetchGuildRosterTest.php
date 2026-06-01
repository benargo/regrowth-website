<?php

namespace Tests\Feature\Jobs;

use App\Http\Integrations\Blizzard\BlizzardConnector;
use App\Http\Integrations\Blizzard\Requests\Guild\GetGuildRosterRequest;
use App\Http\Integrations\Blizzard\Requests\PlayableClass\GetPlayableClassRequest;
use App\Jobs\FetchGuildRoster;
use App\Models\Character;
use App\Models\GuildRank;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Middleware\RateLimitedWithRedis;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Http\Faking\MockResponse;
use Saloon\Laravel\Facades\Saloon;
use Tests\TestCase;

class FetchGuildRosterTest extends TestCase
{
    use RefreshDatabase;

    #[Group('job-contract')]
    #[Test]
    public function it_has_the_correct_tags(): void
    {
        $this->assertSame(['blizzard'], (new FetchGuildRoster)->tags());
    }

    #[Group('job-contract')]
    #[Test]
    public function it_applies_rate_limited_with_redis_middleware(): void
    {
        $middleware = (new FetchGuildRoster)->middleware();

        $this->assertCount(1, $middleware);
        $this->assertInstanceOf(RateLimitedWithRedis::class, $middleware[0]);
    }

    #[Group('job-contract')]
    #[Test]
    public function it_implements_should_queue(): void
    {
        $this->assertInstanceOf(ShouldQueue::class, new FetchGuildRoster);
    }

    #[Group('job-contract')]
    #[Test]
    public function it_uses_batchable(): void
    {
        $this->assertContains(Batchable::class, class_uses_recursive(FetchGuildRoster::class));
    }

    #[Group('handle')]
    #[Test]
    public function it_fetches_the_roster_via_saloon(): void
    {
        GuildRank::factory()->create(['position' => 0]);

        Saloon::fake([
            'battle.net/oauth/token' => MockResponse::make([
                'access_token' => 'test_token',
                'token_type' => 'bearer',
                'expires_in' => 3600,
            ]),
            GetGuildRosterRequest::class => MockResponse::make(body: $this->rosterPayload([
                $this->memberPayload(1, 'Alpha', 70, 1),
            ]), status: 200),
            GetPlayableClassRequest::class => MockResponse::make(body: $this->playableClassPayload(1, 'Warrior'), status: 200),
        ]);

        (new FetchGuildRoster)->handle(app(BlizzardConnector::class));

        Saloon::assertSent(GetGuildRosterRequest::class);
    }

    #[Group('character-synchronisation')]
    #[Test]
    public function it_creates_a_new_character_from_roster_member(): void
    {
        GuildRank::factory()->create(['position' => 0]);

        Saloon::fake([
            'battle.net/oauth/token' => MockResponse::make([
                'access_token' => 'test_token',
                'token_type' => 'bearer',
                'expires_in' => 3600,
            ]),
            GetGuildRosterRequest::class => MockResponse::make(body: $this->rosterPayload([
                $this->memberPayload(999, 'Thrall', 80, 2, 3, 'Orc', 0),
            ]), status: 200),
            GetPlayableClassRequest::class => MockResponse::make(body: $this->playableClassPayload(2, 'Shaman'), status: 200),
        ]);

        (new FetchGuildRoster)->handle(app(BlizzardConnector::class));

        $this->assertDatabaseHas('characters', [
            'id' => 999,
            'name' => 'Thrall',
            'level' => 80,
        ]);
    }

    #[Group('character-synchronisation')]
    #[Test]
    public function it_updates_an_existing_character_from_roster_member(): void
    {
        $rank = GuildRank::factory()->create(['position' => 0]);
        Character::factory()->create(['id' => 999, 'name' => 'OldName', 'level' => 70, 'rank_id' => $rank->id]);

        Saloon::fake([
            'battle.net/oauth/token' => MockResponse::make([
                'access_token' => 'test_token',
                'token_type' => 'bearer',
                'expires_in' => 3600,
            ]),
            GetGuildRosterRequest::class => MockResponse::make(body: $this->rosterPayload([
                $this->memberPayload(999, 'Thrall', 80, 2, 3, 'Orc', 0),
            ]), status: 200),
            GetPlayableClassRequest::class => MockResponse::make(body: $this->playableClassPayload(2, 'Shaman'), status: 200),
        ]);

        (new FetchGuildRoster)->handle(app(BlizzardConnector::class));

        $this->assertDatabaseHas('characters', [
            'id' => 999,
            'name' => 'Thrall',
            'level' => 80,
        ]);
    }

    #[Group('character-synchronisation')]
    #[Test]
    public function it_creates_playable_class_on_demand(): void
    {
        GuildRank::factory()->create(['position' => 0]);

        Saloon::fake([
            'battle.net/oauth/token' => MockResponse::make([
                'access_token' => 'test_token',
                'token_type' => 'bearer',
                'expires_in' => 3600,
            ]),
            GetGuildRosterRequest::class => MockResponse::make(body: $this->rosterPayload([
                $this->memberPayload(1, 'Alpha', 70, 5, 1, 'Human', 0),
            ]), status: 200),
            GetPlayableClassRequest::class => MockResponse::make(body: $this->playableClassPayload(5, 'Priest'), status: 200),
        ]);

        $this->assertDatabaseMissing('playable_classes', ['id' => 5]);

        (new FetchGuildRoster)->handle(app(BlizzardConnector::class));

        $this->assertDatabaseHas('playable_classes', ['id' => 5, 'name' => 'Priest']);
    }

    #[Group('character-synchronisation')]
    #[Test]
    public function it_skips_members_below_level_60(): void
    {
        GuildRank::factory()->create(['position' => 0]);

        Saloon::fake([
            'battle.net/oauth/token' => MockResponse::make([
                'access_token' => 'test_token',
                'token_type' => 'bearer',
                'expires_in' => 3600,
            ]),
            GetGuildRosterRequest::class => MockResponse::make(body: $this->rosterPayload([
                $this->memberPayload(55, 'Lowbie', 59, 1, 1, 'Human', 0),
            ]), status: 200),
        ]);

        (new FetchGuildRoster)->handle(app(BlizzardConnector::class));

        $this->assertDatabaseMissing('characters', ['id' => 55]);
        Saloon::assertNotSent(GetPlayableClassRequest::class);
    }

    #[Group('error-handling')]
    #[Test]
    public function it_logs_and_continues_when_a_character_blizzard_lookup_fails(): void
    {
        GuildRank::factory()->create(['position' => 0]);

        Saloon::fake([
            'battle.net/oauth/token' => MockResponse::make([
                'access_token' => 'test_token',
                'token_type' => 'bearer',
                'expires_in' => 3600,
            ]),
            GetGuildRosterRequest::class => MockResponse::make(body: $this->rosterPayload([
                $this->memberPayload(1, 'FailChar', 70, 1, 1, 'Human', 0),
            ]), status: 200),
            GetPlayableClassRequest::class => MockResponse::make(status: 500),
        ]);

        Log::shouldReceive('warning')->once()->withArgs(function ($message, $context) {
            return $message === 'Failed to sync character from guild roster.'
                && $context['character_id'] === 1;
        });

        (new FetchGuildRoster)->handle(app(BlizzardConnector::class));

        $this->assertDatabaseMissing('characters', ['id' => 1]);
    }

    #[Group('error-handling')]
    #[Test]
    public function it_logs_and_continues_when_guild_rank_is_missing(): void
    {
        Saloon::fake([
            'battle.net/oauth/token' => MockResponse::make([
                'access_token' => 'test_token',
                'token_type' => 'bearer',
                'expires_in' => 3600,
            ]),
            GetGuildRosterRequest::class => MockResponse::make(body: $this->rosterPayload([
                $this->memberPayload(1, 'NoRankChar', 70, 1, 1, 'Human', 99),
            ]), status: 200),
            GetPlayableClassRequest::class => MockResponse::make(body: $this->playableClassPayload(1, 'Warrior'), status: 200),
        ]);

        Log::shouldReceive('warning')->once()->withArgs(function ($message, $context) {
            return $message === 'Failed to sync character from guild roster.'
                && $context['character_id'] === 1;
        });

        (new FetchGuildRoster)->handle(app(BlizzardConnector::class));

        $this->assertDatabaseMissing('characters', ['id' => 1]);
    }

    #[Group('character-synchronisation')]
    #[Test]
    public function it_stores_playable_race_from_roster_link_data_without_extra_api_call(): void
    {
        GuildRank::factory()->create(['position' => 0]);

        Saloon::fake([
            'battle.net/oauth/token' => MockResponse::make([
                'access_token' => 'test_token',
                'token_type' => 'bearer',
                'expires_in' => 3600,
            ]),
            GetGuildRosterRequest::class => MockResponse::make(body: $this->rosterPayload([
                $this->memberPayload(1, 'Alpha', 70, 1, 7, 'Gnome', 0),
            ]), status: 200),
            GetPlayableClassRequest::class => MockResponse::make(body: $this->playableClassPayload(1, 'Warrior'), status: 200),
        ]);

        (new FetchGuildRoster)->handle(app(BlizzardConnector::class));

        $character = Character::find(1);
        $this->assertNotNull($character);
        $this->assertSame(7, $character->playable_race['id']);
        $this->assertSame('Gnome', $character->playable_race['name']);
    }

    #[Group('character-synchronisation')]
    #[Test]
    public function it_touches_every_character_present_in_the_roster(): void
    {
        $rank = GuildRank::factory()->create(['position' => 0]);
        $rosterMember = Character::factory()->create([
            'id' => 500,
            'name' => 'RosterChar',
            'updated_at' => now()->subDays(30),
            'rank_id' => $rank->id,
        ]);

        $absent = Character::factory()->create([
            'updated_at' => now()->subDays(30),
        ]);

        Saloon::fake([
            'battle.net/oauth/token' => MockResponse::make([
                'access_token' => 'test_token',
                'token_type' => 'bearer',
                'expires_in' => 3600,
            ]),
            GetGuildRosterRequest::class => MockResponse::make(body: $this->rosterPayload([
                $this->memberPayload(500, 'RosterChar', 70, 1, 1, 'Human', 0),
            ]), status: 200),
            GetPlayableClassRequest::class => MockResponse::make(body: $this->playableClassPayload(1, 'Warrior'), status: 200),
        ]);

        $beforeMember = $rosterMember->updated_at;
        $beforeAbsent = $absent->updated_at;

        (new FetchGuildRoster)->handle(app(BlizzardConnector::class));

        $this->assertTrue(
            $rosterMember->fresh()->updated_at->greaterThan($beforeMember),
            'Roster character updated_at should advance after the job runs.',
        );

        $this->assertTrue(
            $absent->fresh()->updated_at->equalTo($beforeAbsent),
            'A character absent from the roster should remain untouched.',
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function rosterPayload(array $members = []): array
    {
        return [
            'guild' => [
                'key' => ['href' => 'https://example.test/guild'],
                'name' => 'Wild Growth',
                'id' => 1,
                'realm' => ['key' => ['href' => 'https://example.test/realm'], 'name' => 'Thunderstrike', 'id' => 1, 'slug' => 'thunderstrike'],
            ],
            'members' => $members,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function memberPayload(int $id, string $name, int $level, int $classId = 1, int $raceId = 1, string $raceName = 'Human', int $rank = 0): array
    {
        return [
            'character' => [
                'key' => ['href' => "https://example.test/character/{$id}"],
                'name' => $name,
                'id' => $id,
                'level' => $level,
                'playable_class' => ['key' => ['href' => "https://example.test/class/{$classId}"], 'name' => 'Warrior', 'id' => $classId],
                'playable_race' => ['key' => ['href' => "https://example.test/race/{$raceId}"], 'name' => $raceName, 'id' => $raceId],
                'realm' => ['key' => ['href' => 'https://example.test/realm'], 'name' => 'Thunderstrike', 'id' => 1, 'slug' => 'thunderstrike'],
            ],
            'rank' => $rank,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function playableClassPayload(int $id, string $name): array
    {
        return [
            'id' => $id,
            'name' => $name,
            'gender_name' => ['male' => $name, 'female' => $name],
            'power_type' => ['key' => ['href' => 'https://example.test/power-type/1'], 'name' => 'Rage', 'id' => 1],
            'media' => ['key' => ['href' => "https://example.test/media/class/{$id}"], 'id' => $id],
            'pvp_talent_slots' => ['href' => 'https://example.test/pvp-talent-slots'],
            'playable_races' => [],
        ];
    }

    private function fakeAuth(): void
    {
        Saloon::fake([
            'battle.net/oauth/token' => MockResponse::make([
                'access_token' => 'test_token',
                'token_type' => 'bearer',
                'expires_in' => 3600,
            ]),
        ]);
    }
}
