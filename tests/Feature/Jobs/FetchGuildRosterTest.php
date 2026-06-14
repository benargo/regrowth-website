<?php

namespace Tests\Feature\Jobs;

use App\Enums\Gender;
use App\Events\CharacterUpdated;
use App\Http\Integrations\Blizzard\BlizzardConnector;
use App\Http\Integrations\Blizzard\Requests\Character\GetCharacterProfileRequest;
use App\Http\Integrations\Blizzard\Requests\Guild\GetGuildRosterRequest;
use App\Jobs\FetchGuildRoster;
use App\Models\Character;
use App\Models\GuildRank;
use App\Models\PlayableClass;
use App\Models\PlayableRace;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Middleware\RateLimitedWithRedis;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Http\Faking\MockResponse;
use Saloon\Laravel\Facades\Saloon;
use Tests\Support\Blizzard\HasBlizzardTokenMock;
use Tests\TestCase;

#[Group('characters')]
#[Group('blizzard-integration')]
class FetchGuildRosterTest extends TestCase
{
    use HasBlizzardTokenMock;
    use RefreshDatabase;

    #[Group('contract')]
    #[Test]
    public function it_has_the_correct_tags(): void
    {
        $this->assertSame(['blizzard'], (new FetchGuildRoster)->tags());
    }

    #[Group('contract')]
    #[Test]
    public function it_applies_rate_limited_with_redis_middleware(): void
    {
        $middleware = (new FetchGuildRoster)->middleware();

        $this->assertCount(1, $middleware);
        $this->assertInstanceOf(RateLimitedWithRedis::class, $middleware[0]);
    }

    #[Group('contract')]
    #[Test]
    public function it_implements_should_queue(): void
    {
        $this->assertInstanceOf(ShouldQueue::class, new FetchGuildRoster);
    }

    #[Group('contract')]
    #[Test]
    public function it_uses_batchable(): void
    {
        $this->assertContains(Batchable::class, class_uses_recursive(FetchGuildRoster::class));
    }

    #[Group('happy-path')]
    #[Test]
    public function it_fetches_the_roster_via_saloon(): void
    {
        GuildRank::factory()->create(['position' => 0]);

        Saloon::fake([
            'battle.net/oauth/token' => MockResponse::make($this->makeTokenResponse()),
            GetGuildRosterRequest::class => MockResponse::make(body: $this->rosterPayload([
                $this->memberPayload(1, 'Alpha', 70, 1),
            ]), status: 200),
            GetCharacterProfileRequest::class => MockResponse::make(body: $this->makeProfileResponse(), status: 200),
        ]);

        (new FetchGuildRoster)->handle(app(BlizzardConnector::class));

        Saloon::assertSent(GetGuildRosterRequest::class);
    }

    #[Group('happy-path')]
    #[Test]
    public function it_creates_a_new_character_from_roster_member(): void
    {
        GuildRank::factory()->create(['position' => 0]);
        PlayableClass::factory()->create(['id' => 2, 'name' => 'Shaman']);
        PlayableRace::factory()->create(['id' => 3, 'name' => 'Orc']);

        Saloon::fake([
            'battle.net/oauth/token' => MockResponse::make($this->makeTokenResponse()),
            GetGuildRosterRequest::class => MockResponse::make(body: $this->rosterPayload([
                $this->memberPayload(999, 'Thrall', 80, 0),
            ]), status: 200),
            GetCharacterProfileRequest::class => MockResponse::make(body: $this->makeProfileResponse(classId: 2, raceId: 3), status: 200),
        ]);

        (new FetchGuildRoster)->handle(app(BlizzardConnector::class));

        $this->assertDatabaseHas('characters', [
            'id' => 999,
            'name' => 'Thrall',
            'level' => 80,
        ]);
    }

    #[Group('happy-path')]
    #[Test]
    public function it_updates_an_existing_character_from_roster_member(): void
    {
        $rank = GuildRank::factory()->create(['position' => 0]);
        PlayableClass::factory()->create(['id' => 2, 'name' => 'Shaman']);
        PlayableRace::factory()->create(['id' => 3, 'name' => 'Orc']);
        Character::factory()->create(['id' => 999, 'name' => 'OldName', 'level' => 70, 'rank_id' => $rank->id]);

        Saloon::fake([
            'battle.net/oauth/token' => MockResponse::make($this->makeTokenResponse()),
            GetGuildRosterRequest::class => MockResponse::make(body: $this->rosterPayload([
                $this->memberPayload(999, 'Thrall', 80, 0),
            ]), status: 200),
            GetCharacterProfileRequest::class => MockResponse::make(body: $this->makeProfileResponse(classId: 2, raceId: 3), status: 200),
        ]);

        (new FetchGuildRoster)->handle(app(BlizzardConnector::class));

        $this->assertDatabaseHas('characters', [
            'id' => 999,
            'name' => 'Thrall',
            'level' => 80,
        ]);
    }

    #[Group('happy-path')]
    #[Test]
    public function it_associates_an_existing_local_playable_class(): void
    {
        GuildRank::factory()->create(['position' => 0]);
        PlayableClass::factory()->create(['id' => 5, 'name' => 'Priest']);
        PlayableRace::factory()->create(['id' => 1, 'name' => 'Human']);

        Saloon::fake([
            'battle.net/oauth/token' => MockResponse::make($this->makeTokenResponse()),
            GetGuildRosterRequest::class => MockResponse::make(body: $this->rosterPayload([
                $this->memberPayload(1, 'Alpha', 70, 0),
            ]), status: 200),
            GetCharacterProfileRequest::class => MockResponse::make(body: $this->makeProfileResponse(classId: 5, raceId: 1), status: 200),
        ]);

        (new FetchGuildRoster)->handle(app(BlizzardConnector::class));

        $this->assertSame(5, Character::find(1)->playableClass->id);
    }

    #[Group('happy-path')]
    #[Test]
    public function it_saves_the_character_without_a_class_when_not_in_the_local_table(): void
    {
        GuildRank::factory()->create(['position' => 0]);
        PlayableRace::factory()->create(['id' => 1, 'name' => 'Human']);

        Saloon::fake([
            'battle.net/oauth/token' => MockResponse::make($this->makeTokenResponse()),
            GetGuildRosterRequest::class => MockResponse::make(body: $this->rosterPayload([
                $this->memberPayload(1, 'Alpha', 70, 0),
            ]), status: 200),
            GetCharacterProfileRequest::class => MockResponse::make(body: $this->makeProfileResponse(classId: 5, raceId: 1), status: 200),
        ]);

        $this->assertDatabaseMissing('playable_classes', ['id' => 5]);

        (new FetchGuildRoster)->handle(app(BlizzardConnector::class));

        $character = Character::find(1);
        $this->assertNotNull($character);
        $this->assertNull($character->playableClass);
    }

    #[Group('happy-path')]
    #[Test]
    public function it_skips_members_below_level_60(): void
    {
        GuildRank::factory()->create(['position' => 0]);

        Saloon::fake([
            'battle.net/oauth/token' => MockResponse::make($this->makeTokenResponse()),
            GetGuildRosterRequest::class => MockResponse::make(body: $this->rosterPayload([
                $this->memberPayload(55, 'Lowbie', 59, 0),
            ]), status: 200),
        ]);

        (new FetchGuildRoster)->handle(app(BlizzardConnector::class));

        $this->assertDatabaseMissing('characters', ['id' => 55]);
    }

    #[Group('happy-path')]
    #[Test]
    public function it_stores_playable_race_from_profile_data(): void
    {
        GuildRank::factory()->create(['position' => 0]);
        PlayableClass::factory()->create(['id' => 1, 'name' => 'Warrior']);
        PlayableRace::factory()->create(['id' => 7, 'name' => 'Gnome']);

        Saloon::fake([
            'battle.net/oauth/token' => MockResponse::make($this->makeTokenResponse()),
            GetGuildRosterRequest::class => MockResponse::make(body: $this->rosterPayload([
                $this->memberPayload(1, 'Alpha', 70, 0),
            ]), status: 200),
            GetCharacterProfileRequest::class => MockResponse::make(body: $this->makeProfileResponse(classId: 1, raceId: 7), status: 200),
        ]);

        (new FetchGuildRoster)->handle(app(BlizzardConnector::class));

        $character = Character::find(1);
        $this->assertNotNull($character);
        $this->assertSame(7, $character->playable_race_id);
        $this->assertSame('Gnome', $character->playableRace->name);
    }

    #[Group('happy-path')]
    #[Test]
    public function it_touches_every_character_present_in_the_roster(): void
    {
        $rank = GuildRank::factory()->create(['position' => 0]);
        PlayableClass::factory()->create(['id' => 1, 'name' => 'Warrior']);
        PlayableRace::factory()->create(['id' => 1, 'name' => 'Human']);
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
            'battle.net/oauth/token' => MockResponse::make($this->makeTokenResponse()),
            GetGuildRosterRequest::class => MockResponse::make(body: $this->rosterPayload([
                $this->memberPayload(500, 'RosterChar', 70, 0),
            ]), status: 200),
            GetCharacterProfileRequest::class => MockResponse::make(body: $this->makeProfileResponse(classId: 1, raceId: 1), status: 200),
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

    #[Group('happy-path')]
    #[Test]
    public function it_does_not_dispatch_character_updated_when_syncing(): void
    {
        Event::fake([CharacterUpdated::class]);

        GuildRank::factory()->create(['position' => 0]);

        Saloon::fake([
            'battle.net/oauth/token' => MockResponse::make($this->makeTokenResponse()),
            GetGuildRosterRequest::class => MockResponse::make(body: $this->rosterPayload([
                $this->memberPayload(1, 'Alpha', 70, 0),
            ]), status: 200),
            GetCharacterProfileRequest::class => MockResponse::make(body: $this->makeProfileResponse(), status: 200),
        ]);

        (new FetchGuildRoster)->handle(app(BlizzardConnector::class));

        Event::assertNotDispatched(CharacterUpdated::class);
    }

    #[Group('happy-path')]
    #[Test]
    public function it_stores_male_gender_from_the_character_profile(): void
    {
        GuildRank::factory()->create(['position' => 0]);

        Saloon::fake([
            'battle.net/oauth/token' => MockResponse::make($this->makeTokenResponse()),
            GetGuildRosterRequest::class => MockResponse::make(body: $this->rosterPayload([
                $this->memberPayload(1, 'Thrall', 70, 0),
            ]), status: 200),
            GetCharacterProfileRequest::class => MockResponse::make(body: $this->makeProfileResponse(gender: 'Male'), status: 200),
        ]);

        (new FetchGuildRoster)->handle(app(BlizzardConnector::class));

        $this->assertSame(Gender::MALE, Character::find(1)->gender);
    }

    #[Group('happy-path')]
    #[Test]
    public function it_stores_female_gender_from_the_character_profile(): void
    {
        GuildRank::factory()->create(['position' => 0]);

        Saloon::fake([
            'battle.net/oauth/token' => MockResponse::make($this->makeTokenResponse()),
            GetGuildRosterRequest::class => MockResponse::make(body: $this->rosterPayload([
                $this->memberPayload(2, 'Sylvanas', 70, 0),
            ]), status: 200),
            GetCharacterProfileRequest::class => MockResponse::make(body: $this->makeProfileResponse(gender: 'Female'), status: 200),
        ]);

        (new FetchGuildRoster)->handle(app(BlizzardConnector::class));

        $this->assertSame(Gender::FEMALE, Character::find(2)->gender);
    }

    #[Group('error-handling')]
    #[Test]
    public function it_logs_and_continues_to_the_next_member_when_one_fails(): void
    {
        GuildRank::factory()->create(['position' => 0]);
        PlayableClass::factory()->create(['id' => 1, 'name' => 'Warrior']);
        PlayableRace::factory()->create(['id' => 1, 'name' => 'Human']);

        Saloon::fake([
            'battle.net/oauth/token' => MockResponse::make($this->makeTokenResponse()),
            GetGuildRosterRequest::class => MockResponse::make(body: $this->rosterPayload([
                // First member has an unknown rank (99) and fails on firstOrFail().
                $this->memberPayload(1, 'FailChar', 70, 99),
                // Second member has a valid rank and should still sync.
                $this->memberPayload(2, 'GoodChar', 70, 0),
            ]), status: 200),
            GetCharacterProfileRequest::class => MockResponse::make(body: $this->makeProfileResponse(), status: 200),
        ]);

        Log::shouldReceive('warning')->once()->withArgs(function ($message, $context) {
            return $message === 'Failed to sync character from guild roster.'
                && $context['character_id'] === 1;
        });

        (new FetchGuildRoster)->handle(app(BlizzardConnector::class));

        $this->assertDatabaseMissing('characters', ['id' => 1]);
        $this->assertDatabaseHas('characters', ['id' => 2, 'name' => 'GoodChar']);
    }

    #[Group('error-handling')]
    #[Test]
    public function it_logs_and_continues_when_guild_rank_is_missing(): void
    {
        Saloon::fake([
            'battle.net/oauth/token' => MockResponse::make($this->makeTokenResponse()),
            GetGuildRosterRequest::class => MockResponse::make(body: $this->rosterPayload([
                $this->memberPayload(1, 'NoRankChar', 70, 99),
            ]), status: 200),
            GetCharacterProfileRequest::class => MockResponse::make(body: $this->makeProfileResponse(), status: 200),
        ]);

        Log::shouldReceive('warning')->once()->withArgs(function ($message, $context) {
            return $message === 'Failed to sync character from guild roster.'
                && $context['character_id'] === 1;
        });

        (new FetchGuildRoster)->handle(app(BlizzardConnector::class));

        $this->assertDatabaseMissing('characters', ['id' => 1]);
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
    private function memberPayload(int $id, string $name, int $level, int $rank = 0): array
    {
        return [
            'character' => [
                'key' => ['href' => "https://example.test/character/{$id}"],
                'name' => $name,
                'id' => $id,
                'level' => $level,
                'playable_class' => ['key' => ['href' => 'https://example.test/class/1'], 'name' => 'Warrior', 'id' => 1],
                'playable_race' => ['key' => ['href' => 'https://example.test/race/1'], 'name' => 'Human', 'id' => 1],
                'realm' => ['key' => ['href' => 'https://example.test/realm'], 'name' => 'Thunderstrike', 'id' => 1, 'slug' => 'thunderstrike'],
            ],
            'rank' => $rank,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function makeProfileResponse(string $gender = 'Male', int $classId = 1, int $raceId = 1): array
    {
        return [
            'id' => 1,
            'name' => 'Testcharacter',
            'gender' => ['type' => strtoupper($gender), 'name' => $gender],
            'faction' => ['type' => 'HORDE', 'name' => 'Horde'],
            'race' => ['key' => ['href' => "https://example.test/race/{$raceId}"], 'name' => 'Orc', 'id' => $raceId],
            'character_class' => ['key' => ['href' => "https://example.test/class/{$classId}"], 'name' => 'Shaman', 'id' => $classId],
            'realm' => ['key' => ['href' => 'https://example.test/realm/1'], 'name' => 'Thunderstrike', 'id' => 1, 'slug' => 'thunderstrike'],
            'level' => 70,
            'last_login_timestamp' => 0,
            'average_item_level' => 0,
            'equipped_item_level' => 0,
        ];
    }
}
