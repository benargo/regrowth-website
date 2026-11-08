<?php

namespace Tests\Feature\Jobs;

use App\Enums\Gender;
use App\Http\Integrations\Blizzard\BlizzardConnector;
use App\Http\Integrations\Blizzard\BlizzardNamespace;
use App\Http\Integrations\Blizzard\Requests\Character\GetCharacterProfileRequest;
use App\Http\Integrations\Blizzard\Requests\Guild\GetGuildRosterRequest;
use App\Http\Integrations\Blizzard\Requests\PlayableClass\GetPlayableClassRequest;
use App\Jobs\Addon\BuildExportFile;
use App\Jobs\AttachBlizzardIconToModel;
use App\Jobs\AttachShadowAvatarToPlayableRace;
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
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
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

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake([AttachShadowAvatarToPlayableRace::class]);
    }

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
        PlayableClass::factory()->create(['id' => 1]);
        PlayableRace::factory()->create(['id' => 1]);

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
    public function it_imports_a_class_missing_from_the_local_table(): void
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
        $this->mockGetPlayableClass(5, 'Priest');
        $this->mockGetPlayableClassMedia();
        $this->applyBlizzardMocks();

        $this->assertDatabaseMissing('playable_classes', ['id' => 5]);

        (new FetchGuildRoster($gameVersion->id))->handle(app(BlizzardConnector::class));

        $this->assertSame('Priest', Character::find(1)->playableClass->name);
        $this->assertTrue($gameVersion->playableClasses()->whereKey(5)->exists());
    }

    #[Group('happy-path')]
    #[Test]
    public function it_imports_a_race_missing_from_the_local_table(): void
    {
        $gameVersion = $this->createGameVersion();
        GuildRank::factory()->for($gameVersion)->create(['sort_order' => 0]);
        PlayableClass::factory()->create(['id' => 1, 'name' => 'Warrior']);

        $this->mockGetGuildRoster(['members' => [
            $this->memberPayload(1, 'Alpha', 70, 0),
        ]]);
        $this->mockGetCharacterProfile(responseData: $this->makeCharacterProfileResponse(raceId: 7));
        $this->mockGetPlayableRace(7, 'Gnome');
        $this->applyBlizzardMocks();

        $this->assertDatabaseMissing('playable_races', ['id' => 7]);

        (new FetchGuildRoster($gameVersion->id))->handle(app(BlizzardConnector::class));

        $this->assertSame('Gnome', Character::find(1)->playableRace->name);
        $this->assertTrue($gameVersion->playableRaces()->whereKey(7)->exists());
    }

    #[Group('happy-path')]
    #[Test]
    public function it_imports_from_the_forever_namespace_for_a_forever_version(): void
    {
        $gameVersion = GameVersion::factory()->create(['realm' => null, 'blizzard_namespace' => BlizzardNamespace::FOREVER, 'uses_surnames' => true]);
        GuildRank::factory()->for($gameVersion)->create(['sort_order' => 0]);
        PlayableRace::factory()->create(['id' => 1, 'name' => 'Human']);

        $member = $this->memberPayload(1, 'Thrall', 70, 0);
        $member['character']['surname'] = 'Stormrage';
        $member['character']['slug'] = 'thrall-stormrage';
        $member['character']['faction'] = ['type' => 'HORDE'];
        unset($member['character']['realm'], $member['character']['playable_class']['name'], $member['character']['playable_race']['name']);

        $this->mockGetGuildRoster(['members' => [$member]]);
        $this->mockGetCharacterProfile(responseData: $this->makeCharacterProfileResponse(classId: 5));
        $this->mockGetPlayableClass(5, 'Priest');
        $this->mockGetPlayableClassMedia();
        $this->applyBlizzardMocks();

        (new FetchGuildRoster($gameVersion->id))->handle(app(BlizzardConnector::class));

        Saloon::assertSent(
            fn ($request, $response) => $request instanceof GetPlayableClassRequest
                && $response->getPendingRequest()->headers()->get('Battlenet-Namespace') === 'static-forever-eu',
        );
        $this->assertSame(5, Character::find(1)->playable_class_id);
    }

    #[Group('happy-path')]
    #[Test]
    public function it_links_an_existing_class_and_race_to_the_game_version(): void
    {
        $gameVersion = $this->createGameVersion();
        GuildRank::factory()->for($gameVersion)->create(['sort_order' => 0]);
        PlayableClass::factory()->create(['id' => 1, 'name' => 'Warrior']);
        PlayableRace::factory()->create(['id' => 1, 'name' => 'Human']);

        $this->mockGetGuildRoster(['members' => [
            $this->memberPayload(1, 'Alpha', 70, 0),
        ]]);
        $this->mockGetCharacterProfile();
        $this->applyBlizzardMocks();

        $this->assertFalse($gameVersion->playableClasses()->exists());
        $this->assertFalse($gameVersion->playableRaces()->exists());

        (new FetchGuildRoster($gameVersion->id))->handle(app(BlizzardConnector::class));

        $this->assertTrue($gameVersion->playableClasses()->whereKey(1)->exists());
        $this->assertTrue($gameVersion->playableRaces()->whereKey(1)->exists());
    }

    #[Group('edge-cases')]
    #[Test]
    public function it_stores_only_the_first_name_when_the_version_has_no_surnames(): void
    {
        $gameVersion = $this->createGameVersion();
        GuildRank::factory()->for($gameVersion)->create(['sort_order' => 0]);
        PlayableClass::factory()->create(['id' => 1]);
        PlayableRace::factory()->create(['id' => 1]);

        $this->mockGetGuildRoster(['members' => [
            $this->memberPayload(1, 'Thrall', 70, 0, 'Stormrage'),
        ]]);
        $this->mockGetCharacterProfile();
        $this->mockGetPlayableClassMedia();
        $this->applyBlizzardMocks();

        (new FetchGuildRoster($gameVersion->id))->handle(app(BlizzardConnector::class));

        $this->assertDatabaseHas('characters', ['id' => 1, 'name' => 'Thrall']);
        Saloon::assertSent(fn ($request) => $request instanceof GetCharacterProfileRequest
            && str_ends_with($request->resolveEndpoint(), '/thrall'));
    }

    #[Group('happy-path')]
    #[Test]
    public function it_stores_the_full_name_when_the_version_uses_surnames(): void
    {
        $gameVersion = $this->createGameVersion();
        $gameVersion->update(['uses_surnames' => true]);
        GuildRank::factory()->for($gameVersion)->create(['sort_order' => 0]);
        PlayableClass::factory()->create(['id' => 1]);
        PlayableRace::factory()->create(['id' => 1]);

        $this->mockGetGuildRoster(['members' => [
            $this->memberPayload(1, 'Thrall', 70, 0, 'Stormrage'),
        ]]);
        $this->mockGetCharacterProfile();
        $this->mockGetPlayableClassMedia();
        $this->applyBlizzardMocks();

        (new FetchGuildRoster($gameVersion->id))->handle(app(BlizzardConnector::class));

        $this->assertDatabaseHas('characters', ['id' => 1, 'name' => 'Thrall Stormrage']);
    }

    #[Group('error-handling')]
    #[Test]
    public function it_logs_and_continues_when_two_members_share_a_first_name_without_surnames(): void
    {
        $gameVersion = $this->createGameVersion();
        GuildRank::factory()->for($gameVersion)->create(['sort_order' => 0]);
        PlayableClass::factory()->create(['id' => 1]);
        PlayableRace::factory()->create(['id' => 1]);

        $this->mockGetGuildRoster(['members' => [
            $this->memberPayload(1, 'Thrall', 70, 0, 'Stormrage'),
            $this->memberPayload(2, 'Thrall', 70, 0, 'Doomhammer'),
            $this->memberPayload(3, 'Jaina', 70, 0),
        ]]);
        $this->mockGetCharacterProfile();
        $this->mockGetPlayableClassMedia();
        $this->applyBlizzardMocks();

        Log::shouldReceive('warning')->once()->withArgs(
            fn ($message, $context) => $message === 'Failed to sync character from guild roster.'
                && $context['character_id'] === 2
                && $context['character_name'] === 'Thrall Doomhammer',
        );

        (new FetchGuildRoster($gameVersion->id))->handle(app(BlizzardConnector::class));

        $this->assertDatabaseHas('characters', ['id' => 1, 'name' => 'Thrall']);
        $this->assertDatabaseMissing('characters', ['id' => 2]);
        $this->assertDatabaseHas('characters', ['id' => 3, 'name' => 'Jaina']);
    }

    #[Group('edge-cases')]
    #[Test]
    public function it_queues_icons_for_a_rostered_class_with_no_icons(): void
    {
        Queue::fake();
        Storage::fake('s3');

        $gameVersion = $this->createGameVersion();
        GuildRank::factory()->for($gameVersion)->create(['sort_order' => 0]);
        $playableClass = PlayableClass::factory()->create(['id' => 1]);
        $playableClass->gameVersions()->attach($gameVersion);
        PlayableRace::factory()->create(['id' => 1]);

        $this->mockGetGuildRoster(['members' => [
            $this->memberPayload(1, 'Alpha', 70, 0),
        ]]);
        $this->mockGetCharacterProfile();
        $this->mockGetPlayableClassMedia([
            ['key' => 'icon', 'value' => 'https://render.worldofwarcraft.com/eu/icons/56/class_warrior.jpg'],
        ]);
        $this->applyBlizzardMocks();

        (new FetchGuildRoster($gameVersion->id))->handle(app(BlizzardConnector::class));

        Queue::assertPushed(AttachBlizzardIconToModel::class, 1);
    }

    #[Group('edge-cases')]
    #[Test]
    public function it_requests_a_failing_class_at_most_twice_per_sync(): void
    {
        $gameVersion = $this->createGameVersion();
        GuildRank::factory()->for($gameVersion)->create(['sort_order' => 0]);
        PlayableRace::factory()->create(['id' => 1]);

        $members = collect(['Alpha', 'Bravo', 'Charlie'])->map(function (string $name, int $index): array {
            $member = $this->memberPayload($index + 1, $name, 70, 0);
            $member['character']['playable_class']['id'] = 5;

            return $member;
        });

        $this->mockGetGuildRoster(['members' => $members->all()]);
        $this->mockGetCharacterProfile(responseData: $this->makeCharacterProfileResponse(classId: 5));
        $this->mockGetPlayableClass(status: 500);
        $this->applyBlizzardMocks();

        (new FetchGuildRoster($gameVersion->id))->handle(app(BlizzardConnector::class));

        Saloon::mockClient()->assertSentCount(2, GetPlayableClassRequest::class);
        $this->assertSame(3, Character::whereBelongsTo($gameVersion)->count());
    }

    #[Group('edge-cases')]
    #[Test]
    public function it_keeps_the_existing_class_when_the_import_fails(): void
    {
        $gameVersion = $this->createGameVersion();
        GuildRank::factory()->for($gameVersion)->create(['sort_order' => 0]);
        PlayableClass::factory()->create(['id' => 3, 'name' => 'Hunter']);
        PlayableRace::factory()->create(['id' => 1, 'name' => 'Human']);
        Character::factory()->create([
            'id' => 1,
            'playable_class_id' => 3,
            'playable_race_id' => 1,
            'level' => 60,
            'game_version_id' => $gameVersion->id,
        ]);

        $this->mockGetGuildRoster(['members' => [
            $this->memberPayload(1, 'Alpha', 70, 0),
        ]]);
        $this->mockGetCharacterProfile(responseData: $this->makeCharacterProfileResponse(classId: 5));
        $this->mockGetPlayableClass(status: 500);
        $this->applyBlizzardMocks();

        (new FetchGuildRoster($gameVersion->id))->handle(app(BlizzardConnector::class));

        $character = Character::find(1);
        $this->assertSame(3, $character->playable_class_id);
        $this->assertSame(70, $character->level);
    }

    #[Group('happy-path')]
    #[Test]
    public function it_never_lowers_a_stored_level(): void
    {
        $gameVersion = $this->createGameVersion();
        GuildRank::factory()->for($gameVersion)->create(['sort_order' => 0]);
        PlayableClass::factory()->create(['id' => 1]);
        PlayableRace::factory()->create(['id' => 1]);
        Character::factory()->create(['id' => 1, 'level' => 34, 'game_version_id' => $gameVersion->id]);

        $this->mockGetGuildRoster(['members' => [
            $this->memberPayload(1, 'Alpha', 31, 0),
        ]]);
        $this->mockGetCharacterProfile();
        $this->applyBlizzardMocks();

        (new FetchGuildRoster($gameVersion->id))->handle(app(BlizzardConnector::class));

        $this->assertSame(34, Character::find(1)->level);
    }

    #[Group('happy-path')]
    #[Test]
    public function it_links_the_class_and_race_of_a_member_below_the_minimum_level(): void
    {
        $gameVersion = $this->createGameVersion();
        GuildRank::factory()->for($gameVersion)->create(['sort_order' => 0]);
        PlayableClass::factory()->create(['id' => 1]);
        PlayableRace::factory()->create(['id' => 1]);

        $this->mockGetGuildRoster(['members' => [
            $this->memberPayload(77, 'Newbie', 5, 0),
        ]]);
        $this->mockGetCharacterProfile();
        $this->applyBlizzardMocks();

        (new FetchGuildRoster($gameVersion->id))->handle(app(BlizzardConnector::class));

        $this->assertTrue($gameVersion->playableClasses()->whereKey(1)->exists());
        $this->assertTrue($gameVersion->playableRaces()->whereKey(1)->exists());
        $this->assertDatabaseMissing('characters', ['id' => 77]);
    }

    #[Group('error-handling')]
    #[Test]
    public function it_links_the_class_and_race_of_a_member_whose_profile_request_fails(): void
    {
        $gameVersion = $this->createGameVersion();
        GuildRank::factory()->for($gameVersion)->create(['sort_order' => 0]);
        PlayableClass::factory()->create(['id' => 1]);
        PlayableRace::factory()->create(['id' => 1]);

        $this->mockGetGuildRoster(['members' => [
            $this->memberPayload(78, 'Broken', 70, 0),
        ]]);
        $this->mockGetCharacterProfile(status: 500);
        $this->applyBlizzardMocks();

        (new FetchGuildRoster($gameVersion->id))->handle(app(BlizzardConnector::class));

        $this->assertTrue($gameVersion->playableClasses()->whereKey(1)->exists());
        $this->assertTrue($gameVersion->playableRaces()->whereKey(1)->exists());
        $this->assertDatabaseMissing('characters', ['id' => 78]);
    }

    #[Group('happy-path')]
    #[Test]
    public function it_syncs_members_below_level_60(): void
    {
        $gameVersion = $this->createGameVersion();
        GuildRank::factory()->for($gameVersion)->create(['sort_order' => 0]);
        PlayableClass::factory()->create(['id' => 1]);
        PlayableRace::factory()->create(['id' => 1]);

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
        PlayableClass::factory()->create(['id' => 1]);
        PlayableRace::factory()->create(['id' => 1]);

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
    public function it_does_not_schedule_addon_export_build_when_syncing(): void
    {
        $gameVersion = $this->createGameVersion();
        Bus::fake([BuildExportFile::class]);

        GuildRank::factory()->for($gameVersion)->create(['sort_order' => 0]);

        $this->mockGetGuildRoster(['members' => [
            $this->memberPayload(1, 'Alpha', 70, 0),
        ]]);
        $this->mockGetCharacterProfile();
        $this->applyBlizzardMocks();

        (new FetchGuildRoster($gameVersion->id))->handle(app(BlizzardConnector::class));

        Bus::assertNotDispatched(BuildExportFile::class);
    }

    #[Group('happy-path')]
    #[Test]
    public function it_stores_male_gender_from_the_character_profile(): void
    {
        $gameVersion = $this->createGameVersion();
        GuildRank::factory()->for($gameVersion)->create(['sort_order' => 0]);
        PlayableClass::factory()->create(['id' => 1]);
        PlayableRace::factory()->create(['id' => 1]);

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
        PlayableClass::factory()->create(['id' => 1]);
        PlayableRace::factory()->create(['id' => 1]);

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
        $this->mockGetPlayableClassMedia();
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
        PlayableClass::factory()->create(['id' => 1]);
        PlayableRace::factory()->create(['id' => 1]);

        $this->mockGetGuildRoster(['members' => [
            $this->memberPayload(1, 'NoRankChar', 70, 99),
        ]]);
        $this->mockGetCharacterProfile();
        $this->mockGetPlayableClassMedia();
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

    // ==================== forever ====================

    #[Group('happy-path')]
    #[Test]
    public function it_syncs_a_realmless_forever_member_under_their_full_name(): void
    {
        $gameVersion = GameVersion::factory()->create(['realm' => null, 'blizzard_namespace' => BlizzardNamespace::FOREVER, 'uses_surnames' => true]);
        GuildRank::factory()->for($gameVersion)->create(['sort_order' => 0]);
        PlayableClass::factory()->create(['id' => 1]);
        PlayableRace::factory()->create(['id' => 1]);

        $member = $this->memberPayload(1, 'Thrall', 70, 0);
        $member['character']['surname'] = 'Stormrage';
        $member['character']['slug'] = 'thrall-stormrage';
        $member['character']['faction'] = ['type' => 'HORDE'];
        unset($member['character']['realm'], $member['character']['playable_class']['name'], $member['character']['playable_race']['name']);

        $this->mockGetGuildRoster(['members' => [$member]]);
        $this->mockGetCharacterProfile();
        $this->applyBlizzardMocks();

        (new FetchGuildRoster($gameVersion->id))->handle(app(BlizzardConnector::class));

        Saloon::assertSent(fn ($request) => $request instanceof GetGuildRosterRequest
            && $request->resolveEndpoint() === "/data/wow/guild/{$gameVersion->guild_slug}/roster");
        Saloon::assertSent(fn ($request) => $request instanceof GetCharacterProfileRequest
            && $request->resolveEndpoint() === '/profile/wow/character/thrall-stormrage');
        $this->assertDatabaseHas('characters', ['id' => 1, 'name' => 'Thrall Stormrage', 'game_version_id' => $gameVersion->id]);
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
    private function memberPayload(int $id, string $name, int $level, int $rank = 0, ?string $surname = null): array
    {
        return array_replace_recursive([
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
        ], $surname === null ? [] : ['character' => ['surname' => $surname]]);
    }
}
