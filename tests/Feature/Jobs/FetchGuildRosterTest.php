<?php

namespace Tests\Feature\Jobs;

use App\Enums\Gender;
use App\Events\CharacterUpdated;
use App\Http\Integrations\Blizzard\BlizzardConnector;
use App\Http\Integrations\Blizzard\BlizzardNamespace;
use App\Http\Integrations\Blizzard\Requests\Character\GetCharacterProfileRequest;
use App\Http\Integrations\Blizzard\Requests\Guild\GetGuildRosterRequest;
use App\Jobs\FetchGuildRoster;
use App\Models\Character;
use App\Models\GameVersion;
use App\Models\GuildRank;
use App\Models\PlayableClass;
use App\Models\PlayableRace;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Middleware\RateLimitedWithRedis;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Laravel\Facades\Saloon;
use Tests\Support\Blizzard\MocksBlizzardServices;
use Tests\TestCase;

#[Group('characters')]
#[Group('blizzard-integration')]
class FetchGuildRosterTest extends TestCase
{
    use MocksBlizzardServices;
    use RefreshDatabase;

    // ==================== job contract ====================

    #[Group('contract')]
    #[Test]
    public function it_has_the_correct_tags(): void
    {
        $this->assertSame(['blizzard', 'game-version:7'], (new FetchGuildRoster(7))->tags());
    }

    #[Group('contract')]
    #[Test]
    public function it_applies_rate_limited_with_redis_middleware(): void
    {
        $middleware = (new FetchGuildRoster(1))->middleware();

        $this->assertCount(1, $middleware);
        $this->assertInstanceOf(RateLimitedWithRedis::class, $middleware[0]);
    }

    #[Group('contract')]
    #[Test]
    public function it_skips_the_rate_limiter_when_bypassing(): void
    {
        $this->assertSame([], (new FetchGuildRoster(1, bypassRateLimit: true))->middleware());
    }

    #[Group('contract')]
    #[Test]
    public function it_implements_should_queue(): void
    {
        $this->assertInstanceOf(ShouldQueue::class, new FetchGuildRoster(1));
    }

    #[Group('contract')]
    #[Test]
    public function it_uses_batchable(): void
    {
        $this->assertContains(Batchable::class, class_uses_recursive(FetchGuildRoster::class));
    }

    // ==================== fetching and creating characters ====================

    #[Group('happy-path')]
    #[Test]
    public function it_requests_the_roster_for_the_game_versions_realm_namespace_and_guild(): void
    {
        $gameVersion = $this->createGameVersion();
        $gameVersion->update(['guild_name' => 'Sister Guild']);
        GuildRank::factory()->for($gameVersion)->create(['sort_order' => 0]);

        $this->mockGetGuildRoster();
        $this->applyBlizzardMocks();

        $blizzard = app(BlizzardConnector::class);
        (new FetchGuildRoster($gameVersion->id))->handle($blizzard);

        $expectedNamespace = BlizzardNamespace::ERA->forProfileRequests($blizzard->getRegion());

        Saloon::assertSent(fn ($request, $response) => $request instanceof GetGuildRosterRequest
            && $request->resolveEndpoint() === '/data/wow/guild/spineshatter/sister-guild/roster'
            && $response->getPendingRequest()->headers()->get('Battlenet-Namespace') === $expectedNamespace);
    }

    #[Group('happy-path')]
    #[Test]
    public function it_requests_character_profiles_with_the_game_versions_realm_and_namespace(): void
    {
        $gameVersion = $this->createGameVersion();
        GuildRank::factory()->for($gameVersion)->create(['sort_order' => 0]);

        $this->mockGetGuildRoster(['members' => [
            $this->memberPayload(1, 'Alpha', 70, 0),
        ]]);
        $this->mockGetCharacterProfile();
        $this->applyBlizzardMocks();

        $blizzard = app(BlizzardConnector::class);
        (new FetchGuildRoster($gameVersion->id))->handle($blizzard);

        $expectedNamespace = BlizzardNamespace::ERA->forProfileRequests($blizzard->getRegion());

        Saloon::assertSent(fn ($request, $response) => $request instanceof GetCharacterProfileRequest
            && str_starts_with($request->resolveEndpoint(), '/profile/wow/character/spineshatter/')
            && $response->getPendingRequest()->headers()->get('Battlenet-Namespace') === $expectedNamespace);
    }

    #[Test]
    public function it_requests_the_profile_of_an_accented_member_by_its_accented_name(): void
    {
        $gameVersion = $this->createGameVersion();
        GuildRank::factory()->for($gameVersion)->create(['sort_order' => 0]);

        $this->mockGetGuildRoster(['members' => [
            $this->memberPayload(1, 'Draégo', 70, 0),
        ]]);
        $this->mockGetCharacterProfile();
        $this->applyBlizzardMocks();

        (new FetchGuildRoster($gameVersion->id))->handle(app(BlizzardConnector::class));

        Saloon::assertSent(fn ($request) => $request instanceof GetCharacterProfileRequest
            && $request->resolveEndpoint() === '/profile/wow/character/spineshatter/draégo');
    }

    #[Group('happy-path')]
    #[Test]
    public function it_creates_a_new_character_from_roster_member(): void
    {
        $gameVersion = $this->createGameVersion();
        GuildRank::factory()->for($gameVersion)->create(['sort_order' => 0]);
        PlayableClass::factory()->create(['id' => 2, 'name' => 'Shaman']);
        PlayableRace::factory()->create(['id' => 3, 'name' => 'Orc']);

        $this->mockGetGuildRoster(['members' => [
            $this->memberPayload(999, 'Thrall', 80, 0),
        ]]);
        $this->mockGetCharacterProfile(responseData: [
            'character_class' => ['key' => ['href' => 'https://example.test/class/2'], 'name' => 'Shaman', 'id' => 2],
            'race' => ['key' => ['href' => 'https://example.test/race/3'], 'name' => 'Orc', 'id' => 3],
        ]);
        $this->applyBlizzardMocks();

        (new FetchGuildRoster($gameVersion->id))->handle(app(BlizzardConnector::class));

        $this->assertDatabaseHas('characters', [
            'id' => 999,
            'name' => 'Thrall',
            'level' => 80,
        ]);
    }

    #[Group('happy-path')]
    #[Test]
    public function it_sets_the_game_version_on_synced_characters(): void
    {
        $gameVersion = $this->createGameVersion();
        GuildRank::factory()->for($gameVersion)->create(['sort_order' => 0]);

        $this->mockGetGuildRoster(['members' => [
            $this->memberPayload(999, 'Thrall', 80, 0),
        ]]);
        $this->mockGetCharacterProfile();
        $this->applyBlizzardMocks();

        (new FetchGuildRoster($gameVersion->id))->handle(app(BlizzardConnector::class));

        $this->assertDatabaseHas('characters', [
            'id' => 999,
            'game_version_id' => $gameVersion->id,
        ]);
    }

    #[Group('happy-path')]
    #[Test]
    public function it_updates_an_existing_character_from_roster_member(): void
    {
        $gameVersion = $this->createGameVersion();
        $rank = GuildRank::factory()->for($gameVersion)->create(['sort_order' => 0]);
        PlayableClass::factory()->create(['id' => 2, 'name' => 'Shaman']);
        PlayableRace::factory()->create(['id' => 3, 'name' => 'Orc']);
        Character::factory()->create(['id' => 999, 'name' => 'OldName', 'level' => 70, 'rank_id' => $rank->id]);

        $this->mockGetGuildRoster(['members' => [
            $this->memberPayload(999, 'Thrall', 80, 0),
        ]]);
        $this->mockGetCharacterProfile(responseData: [
            'character_class' => ['key' => ['href' => 'https://example.test/class/2'], 'name' => 'Shaman', 'id' => 2],
            'race' => ['key' => ['href' => 'https://example.test/race/3'], 'name' => 'Orc', 'id' => 3],
        ]);
        $this->applyBlizzardMocks();

        (new FetchGuildRoster($gameVersion->id))->handle(app(BlizzardConnector::class));

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
        $gameVersion = $this->createGameVersion();
        GuildRank::factory()->for($gameVersion)->create(['sort_order' => 0]);
        PlayableClass::factory()->create(['id' => 5, 'name' => 'Priest']);
        PlayableRace::factory()->create(['id' => 1, 'name' => 'Human']);

        $this->mockGetGuildRoster(['members' => [
            $this->memberPayload(1, 'Alpha', 70, 0),
        ]]);
        $this->mockGetCharacterProfile(responseData: [
            'character_class' => ['key' => ['href' => 'https://example.test/class/5'], 'name' => 'Priest', 'id' => 5],
            'race' => ['key' => ['href' => 'https://example.test/race/1'], 'name' => 'Human', 'id' => 1],
        ]);
        $this->applyBlizzardMocks();

        (new FetchGuildRoster($gameVersion->id))->handle(app(BlizzardConnector::class));

        $this->assertSame(5, Character::find(1)->playableClass->id);
    }

    #[Group('happy-path')]
    #[Test]
    public function it_saves_the_character_without_a_class_when_not_in_the_local_table(): void
    {
        $gameVersion = $this->createGameVersion();
        GuildRank::factory()->for($gameVersion)->create(['sort_order' => 0]);
        PlayableRace::factory()->create(['id' => 1, 'name' => 'Human']);

        $this->mockGetGuildRoster(['members' => [
            $this->memberPayload(1, 'Alpha', 70, 0),
        ]]);
        $this->mockGetCharacterProfile(responseData: [
            'character_class' => ['key' => ['href' => 'https://example.test/class/5'], 'name' => 'Priest', 'id' => 5],
            'race' => ['key' => ['href' => 'https://example.test/race/1'], 'name' => 'Human', 'id' => 1],
        ]);
        $this->applyBlizzardMocks();

        $this->assertDatabaseMissing('playable_classes', ['id' => 5]);

        (new FetchGuildRoster($gameVersion->id))->handle(app(BlizzardConnector::class));

        $character = Character::find(1);
        $this->assertNotNull($character);
        $this->assertNull($character->playableClass);
    }

    #[Group('happy-path')]
    #[Test]
    public function it_syncs_members_below_level_60(): void
    {
        $gameVersion = $this->createGameVersion();
        GuildRank::factory()->for($gameVersion)->create(['sort_order' => 0]);

        $this->mockGetGuildRoster(['members' => [
            $this->memberPayload(55, 'Lowbie', 59, 0),
        ]]);
        $this->mockGetCharacterProfile();
        $this->applyBlizzardMocks();

        (new FetchGuildRoster($gameVersion->id))->handle(app(BlizzardConnector::class));

        $this->assertDatabaseHas('characters', ['id' => 55, 'level' => 59]);
    }

    #[Test]
    public function it_skips_members_below_level_10(): void
    {
        $gameVersion = $this->createGameVersion();
        GuildRank::factory()->for($gameVersion)->create(['sort_order' => 0]);

        $this->mockGetGuildRoster(['members' => [
            $this->memberPayload(56, 'Fresh', 9, 0),
        ]]);
        $this->applyBlizzardMocks();

        (new FetchGuildRoster($gameVersion->id))->handle(app(BlizzardConnector::class));

        $this->assertDatabaseMissing('characters', ['id' => 56]);
        Saloon::assertNotSent(GetCharacterProfileRequest::class);
    }

    #[Group('happy-path')]
    #[Test]
    public function it_stores_playable_race_from_profile_data(): void
    {
        $gameVersion = $this->createGameVersion();
        GuildRank::factory()->for($gameVersion)->create(['sort_order' => 0]);
        PlayableClass::factory()->create(['id' => 1, 'name' => 'Warrior']);
        PlayableRace::factory()->create(['id' => 7, 'name' => 'Gnome']);

        $this->mockGetGuildRoster(['members' => [
            $this->memberPayload(1, 'Alpha', 70, 0),
        ]]);
        $this->mockGetCharacterProfile(responseData: [
            'character_class' => ['key' => ['href' => 'https://example.test/class/1'], 'name' => 'Warrior', 'id' => 1],
            'race' => ['key' => ['href' => 'https://example.test/race/7'], 'name' => 'Gnome', 'id' => 7],
        ]);
        $this->applyBlizzardMocks();

        (new FetchGuildRoster($gameVersion->id))->handle(app(BlizzardConnector::class));

        $character = Character::find(1);
        $this->assertNotNull($character);
        $this->assertSame(7, $character->playable_race_id);
        $this->assertSame('Gnome', $character->playableRace->name);
    }

    #[Test]
    public function it_assigns_the_rank_at_that_index_within_the_game_version(): void
    {
        $gameVersion = $this->createGameVersion();
        GuildRank::factory()->for(GameVersion::factory())->create(['sort_order' => 0, 'name' => 'Elsewhere']);
        $rank = GuildRank::factory()->for($gameVersion)->create(['sort_order' => 0]);

        $this->mockGetGuildRoster(['members' => [
            $this->memberPayload(1, 'Alpha', 70, 0),
        ]]);
        $this->mockGetCharacterProfile();
        $this->applyBlizzardMocks();

        (new FetchGuildRoster($gameVersion->id))->handle(app(BlizzardConnector::class));

        $this->assertSame($rank->id, Character::find(1)->rank_id);
    }

    // ==================== updating existing characters ====================

    #[Group('happy-path')]
    #[Test]
    public function it_touches_every_character_present_in_the_roster(): void
    {
        $gameVersion = $this->createGameVersion();
        $rank = GuildRank::factory()->for($gameVersion)->create(['sort_order' => 0]);
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

        $this->mockGetGuildRoster(['members' => [
            $this->memberPayload(500, 'RosterChar', 70, 0),
        ]]);
        $this->mockGetCharacterProfile();
        $this->applyBlizzardMocks();

        $beforeMember = $rosterMember->updated_at;
        $beforeAbsent = $absent->updated_at;

        (new FetchGuildRoster($gameVersion->id))->handle(app(BlizzardConnector::class));

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
        $gameVersion = $this->createGameVersion();
        Event::fake([CharacterUpdated::class]);

        GuildRank::factory()->for($gameVersion)->create(['sort_order' => 0]);

        $this->mockGetGuildRoster(['members' => [
            $this->memberPayload(1, 'Alpha', 70, 0),
        ]]);
        $this->mockGetCharacterProfile();
        $this->applyBlizzardMocks();

        (new FetchGuildRoster($gameVersion->id))->handle(app(BlizzardConnector::class));

        Event::assertNotDispatched(CharacterUpdated::class);
    }

    #[Group('happy-path')]
    #[Test]
    public function it_stores_male_gender_from_the_character_profile(): void
    {
        $gameVersion = $this->createGameVersion();
        GuildRank::factory()->for($gameVersion)->create(['sort_order' => 0]);

        $this->mockGetGuildRoster(['members' => [
            $this->memberPayload(1, 'Thrall', 70, 0),
        ]]);
        $this->mockGetCharacterProfile(gender: 'Male');
        $this->applyBlizzardMocks();

        (new FetchGuildRoster($gameVersion->id))->handle(app(BlizzardConnector::class));

        $this->assertSame(Gender::MALE, Character::find(1)->gender);
    }

    #[Group('happy-path')]
    #[Test]
    public function it_stores_female_gender_from_the_character_profile(): void
    {
        $gameVersion = $this->createGameVersion();
        GuildRank::factory()->for($gameVersion)->create(['sort_order' => 0]);

        $this->mockGetGuildRoster(['members' => [
            $this->memberPayload(2, 'Sylvanas', 70, 0),
        ]]);
        $this->mockGetCharacterProfile(gender: 'Female');
        $this->applyBlizzardMocks();

        (new FetchGuildRoster($gameVersion->id))->handle(app(BlizzardConnector::class));

        $this->assertSame(Gender::FEMALE, Character::find(2)->gender);
    }

    // ==================== error handling ====================

    #[Group('error-handling')]
    #[Test]
    public function it_fails_when_the_game_version_does_not_exist(): void
    {
        $this->applyBlizzardMocks();

        $this->expectException(ModelNotFoundException::class);

        (new FetchGuildRoster(999))->handle(app(BlizzardConnector::class));
    }

    #[Group('error-handling')]
    #[Test]
    public function it_logs_and_continues_to_the_next_member_when_one_fails(): void
    {
        $gameVersion = $this->createGameVersion();
        GuildRank::factory()->for($gameVersion)->create(['sort_order' => 0]);
        PlayableClass::factory()->create(['id' => 1, 'name' => 'Warrior']);
        PlayableRace::factory()->create(['id' => 1, 'name' => 'Human']);

        $this->mockGetGuildRoster(['members' => [
            // First member has an unknown rank (99) and fails on firstOrFail().
            $this->memberPayload(1, 'FailChar', 70, 99),
            // Second member has a valid rank and should still sync.
            $this->memberPayload(2, 'GoodChar', 70, 0),
        ]]);
        $this->mockGetCharacterProfile();
        $this->applyBlizzardMocks();

        Log::shouldReceive('warning')->once()->withArgs(function ($message, $context) {
            return $message === 'Failed to sync character from guild roster.'
                && $context['character_id'] === 1;
        });

        (new FetchGuildRoster($gameVersion->id))->handle(app(BlizzardConnector::class));

        $this->assertDatabaseMissing('characters', ['id' => 1]);
        $this->assertDatabaseHas('characters', ['id' => 2, 'name' => 'GoodChar']);
    }

    #[Group('error-handling')]
    #[Test]
    public function it_logs_and_continues_when_guild_rank_is_missing(): void
    {
        $gameVersion = $this->createGameVersion();
        GuildRank::factory()->for($gameVersion)->create(['sort_order' => 0]);

        $this->mockGetGuildRoster(['members' => [
            $this->memberPayload(1, 'NoRankChar', 70, 99),
        ]]);
        $this->mockGetCharacterProfile();
        $this->applyBlizzardMocks();

        Log::shouldReceive('warning')->once()->withArgs(function ($message, $context) {
            return $message === 'Failed to sync character from guild roster.'
                && $context['character_id'] === 1;
        });

        (new FetchGuildRoster($gameVersion->id))->handle(app(BlizzardConnector::class));

        $this->assertDatabaseMissing('characters', ['id' => 1]);
        Saloon::assertNotSent(GetCharacterProfileRequest::class);
    }

    #[Group('error-handling')]
    #[Test]
    public function it_logs_once_and_skips_the_roster_when_the_game_version_has_no_guild_ranks(): void
    {
        $gameVersion = $this->createGameVersion();
        GuildRank::factory()->for(GameVersion::factory())->create(['sort_order' => 0]);

        $this->mockGetGuildRoster(['members' => [
            $this->memberPayload(1, 'Alpha', 70, 0),
            $this->memberPayload(2, 'Bravo', 70, 0),
        ]]);
        $this->mockGetCharacterProfile();
        $this->applyBlizzardMocks();

        Log::shouldReceive('warning')->once()->withArgs(function ($message, $context) use ($gameVersion) {
            return $message === 'Skipped guild roster sync: the game version has no guild ranks.'
                && $context['game_version_id'] === $gameVersion->id;
        });

        (new FetchGuildRoster($gameVersion->id))->handle(app(BlizzardConnector::class));

        Saloon::assertNotSent(GetGuildRosterRequest::class);
        Saloon::assertNotSent(GetCharacterProfileRequest::class);
        $this->assertDatabaseCount('characters', 0);
    }

    // ==================== helpers ====================

    private function createGameVersion(): GameVersion
    {
        return GameVersion::factory()->create([
            'realm' => 'Spineshatter',
            'blizzard_namespace' => BlizzardNamespace::ERA,
        ]);
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
}
