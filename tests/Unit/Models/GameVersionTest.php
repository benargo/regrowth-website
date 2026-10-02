<?php

namespace Tests\Unit\Models;

use App\Casts\AsSlug;
use App\Casts\AsTheme;
use App\Contracts\Models\DatasetModel;
use App\Enums\Faction;
use App\Enums\Theme;
use App\Http\Integrations\Blizzard\BlizzardNamespace;
use App\Http\Integrations\WarcraftLogs\WarcraftLogsNamespace;
use App\Models\Character;
use App\Models\GameVersion;
use App\Models\GuildRank;
use App\Models\Item;
use App\Models\Phase;
use App\Models\PlayableClass;
use App\Models\PlayableRace;
use App\Models\Raid;
use App\Models\User;
use App\Models\WarcraftLogs\GuildTag;
use App\Policies\DatasetPolicy;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\ModelTestCase;

#[Group('platform')]
class GameVersionTest extends ModelTestCase
{
    protected function modelClass(): string
    {
        return GameVersion::class;
    }

    #[Test]
    public function it_uses_game_versions_table(): void
    {
        $model = new GameVersion;

        $this->assertSame('game_versions', $model->getTable());
    }

    #[Test]
    public function it_uses_auto_incrementing_id(): void
    {
        $model = new GameVersion;

        $this->assertSame('id', $model->getKeyName());
        $this->assertTrue($model->getIncrementing());
    }

    #[Test]
    public function it_declares_fillable_via_attribute(): void
    {
        $model = new GameVersion;

        $this->assertFillableAttribute($model, [
            'title',
            'slug',
            'realm',
            'guild_name',
            'faction',
            'release_date',
            'theme',
            'blizzard_namespace',
            'warcraftlogs_guild',
            'warcraftlogs_namespace',
        ]);
    }

    #[Test]
    public function it_has_expected_casts(): void
    {
        $model = new GameVersion;

        $this->assertCasts($model, [
            'slug' => AsSlug::class,
            'faction' => Faction::class,
            'release_date' => 'datetime',
            'theme' => AsTheme::class,
            'blizzard_namespace' => BlizzardNamespace::class,
            'warcraftlogs_guild' => 'integer',
            'warcraftlogs_namespace' => WarcraftLogsNamespace::class,
        ]);
    }

    #[Test]
    public function it_is_a_dataset_model_governed_by_the_dataset_policy(): void
    {
        $this->assertInstanceOf(DatasetModel::class, new GameVersion);

        $attributes = (new \ReflectionClass(GameVersion::class))->getAttributes(UsePolicy::class);

        $this->assertNotEmpty($attributes);
        $this->assertSame(DatasetPolicy::class, $attributes[0]->newInstance()->class);
    }

    #[Test]
    public function factory_creates_valid_model(): void
    {
        $gameVersion = $this->create();

        $this->assertNotEmpty($gameVersion->title);
        $this->assertModelExists($gameVersion);
    }

    #[Test]
    #[Group('edge-case')]
    public function it_allows_nullable_fields_to_be_null(): void
    {
        $gameVersion = $this->create([
            'realm' => null,
            'faction' => null,
            'blizzard_namespace' => null,
            'warcraftlogs_guild' => null,
            'warcraftlogs_namespace' => null,
        ])->fresh();

        $this->assertNull($gameVersion->realm);
        $this->assertNull($gameVersion->faction);
        $this->assertNull($gameVersion->blizzard_namespace);
        $this->assertNull($gameVersion->warcraftlogs_guild);
        $this->assertNull($gameVersion->warcraftlogs_namespace);
    }

    // ==================== casts ====================

    #[Test]
    public function slug_is_stored_as_a_slug(): void
    {
        $gameVersion = $this->create(['slug' => 'TBC Anniversary']);

        $this->assertSame('tbc-anniversary', $gameVersion->fresh()->slug);
    }

    #[Test]
    public function faction_is_cast_to_faction_enum(): void
    {
        $gameVersion = $this->create(['faction' => Faction::HORDE]);

        $this->assertSame(Faction::HORDE, $gameVersion->fresh()->faction);
    }

    #[Test]
    public function blizzard_namespace_is_cast_to_blizzard_namespace_enum(): void
    {
        $gameVersion = $this->create(['blizzard_namespace' => BlizzardNamespace::CLASSIC]);

        $this->assertSame(BlizzardNamespace::CLASSIC, $gameVersion->fresh()->blizzard_namespace);
    }

    #[Test]
    #[Group('error-handling')]
    public function blizzard_namespace_throws_for_a_value_outside_the_enum(): void
    {
        $gameVersion = $this->create();

        DB::table('game_versions')
            ->where('id', $gameVersion->id)
            ->update(['blizzard_namespace' => 'some-future-namespace']);

        $this->expectException(\ValueError::class);

        $gameVersion->fresh()->blizzard_namespace;
    }

    #[Test]
    public function theme_is_cast_to_theme_enum(): void
    {
        $gameVersion = $this->create(['theme' => Theme::FOREVER]);

        $this->assertSame(Theme::FOREVER, $gameVersion->fresh()->theme);
    }

    #[Test]
    #[Group('error-handling')]
    public function theme_throws_for_a_value_outside_the_enum(): void
    {
        $gameVersion = $this->create();

        DB::table('game_versions')
            ->where('id', $gameVersion->id)
            ->update(['theme' => 'some-future-theme']);

        $this->expectException(\ValueError::class);

        $gameVersion->fresh()->theme;
    }

    #[Test]
    public function theme_defaults_to_the_default_theme_when_set_to_null(): void
    {
        $gameVersion = $this->create(['theme' => null]);

        $this->assertSame(Theme::default(), $gameVersion->theme);
        $this->assertTableHas([
            'id' => $gameVersion->id,
            'theme' => Theme::default()->value,
        ]);
    }

    #[Test]
    public function warcraftlogs_guild_is_cast_to_an_integer(): void
    {
        $gameVersion = $this->create(['warcraftlogs_guild' => '774848'])->fresh();

        $this->assertSame(774848, $gameVersion->warcraftlogs_guild);
    }

    #[Test]
    public function warcraftlogs_namespace_is_cast_to_warcraftlogs_namespace_enum(): void
    {
        $gameVersion = $this->create(['warcraftlogs_namespace' => WarcraftLogsNamespace::SEASON_OF_DISCOVERY]);

        $this->assertSame(WarcraftLogsNamespace::SEASON_OF_DISCOVERY, $gameVersion->fresh()->warcraftlogs_namespace);
        $this->assertTableHas([
            'id' => $gameVersion->id,
            'warcraftlogs_namespace' => 'season_of_discovery',
        ]);
    }

    #[Test]
    #[Group('error-handling')]
    public function warcraftlogs_namespace_throws_for_a_value_outside_the_enum(): void
    {
        $gameVersion = $this->create();

        DB::table('game_versions')
            ->where('id', $gameVersion->id)
            ->update(['warcraftlogs_namespace' => 'ANNIVERSARY']);

        $this->expectException(\ValueError::class);

        $gameVersion->fresh()->warcraftlogs_namespace;
    }

    #[Test]
    public function release_date_round_trips_without_a_timezone_shift(): void
    {
        $releaseDate = Carbon::create(2026, 2, 6, 0, 0, 0, config('app.timezone'));

        $gameVersion = $this->create(['release_date' => $releaseDate])->fresh();

        $this->assertInstanceOf(Carbon::class, $gameVersion->release_date);
        $this->assertTrue($releaseDate->eq($gameVersion->release_date));
    }

    // ==================== accessors ====================

    #[Test]
    public function it_computes_guild_slug_from_guild_name(): void
    {
        $gameVersion = $this->make(['guild_name' => 'the Old Guard']);

        $this->assertSame('the-old-guard', $gameVersion->guild_slug);
    }

    #[Test]
    public function it_computes_realm_slug_from_realm(): void
    {
        $gameVersion = $this->make(['realm' => 'Gehennas']);

        $this->assertSame('gehennas', $gameVersion->realm_slug);
    }

    #[Test]
    #[Group('edge-case')]
    public function it_returns_a_null_realm_slug_when_realm_is_null(): void
    {
        $gameVersion = $this->make(['realm' => null]);

        $this->assertNull($gameVersion->realm_slug);
    }

    // ==================== relationships ====================

    /**
     * @param  class-string<Model>  $relatedModel
     */
    #[Test]
    #[DataProvider('usageRelations')]
    public function it_has_many_usage_relations(string $relation, string $relatedModel): void
    {
        $gameVersion = $this->create();
        $related = $relatedModel::factory()->for($gameVersion)->create();

        $this->assertRelation($gameVersion, $relation, HasMany::class);
        $this->assertTrue($gameVersion->{$relation}->contains($related));
    }

    #[Test]
    public function it_has_many_raids_through_phases(): void
    {
        $gameVersion = $this->create();
        $phase = Phase::factory()->for($gameVersion)->create();
        $raid = Raid::factory()->for($phase)->create();

        $this->assertRelation($gameVersion, 'raids', HasManyThrough::class);
        $this->assertTrue($gameVersion->raids->contains($raid));
    }

    #[Test]
    public function it_has_many_guild_tags_through_phases(): void
    {
        $gameVersion = $this->create();
        $phase = Phase::factory()->for($gameVersion)->create();
        $guildTag = GuildTag::factory()->withPhase($phase)->create();
        $otherVersionsTag = GuildTag::factory()->withPhase(Phase::factory()->for(GameVersion::factory())->create())->create();
        $phaselessTag = GuildTag::factory()->withoutPhase()->create();

        $this->assertRelation($gameVersion, 'guildTags', HasManyThrough::class);
        $this->assertTrue($gameVersion->guildTags->contains($guildTag));
        $this->assertFalse($gameVersion->guildTags->contains($otherVersionsTag));
        $this->assertFalse($gameVersion->guildTags->contains($phaselessTag));
    }

    #[Test]
    public function it_belongs_to_many_playable_races(): void
    {
        $gameVersion = $this->create();
        $race = PlayableRace::factory()->create();

        $gameVersion->playableRaces()->attach($race);

        $this->assertRelation($gameVersion, 'playableRaces', BelongsToMany::class);
        $this->assertTrue($gameVersion->playableRaces->contains($race));
        $this->assertTrue($race->gameVersions->contains($gameVersion));
    }

    #[Test]
    public function it_belongs_to_many_playable_classes(): void
    {
        $gameVersion = $this->create();
        $class = PlayableClass::factory()->create();

        $gameVersion->playableClasses()->attach($class);

        $this->assertRelation($gameVersion, 'playableClasses', BelongsToMany::class);
        $this->assertTrue($gameVersion->playableClasses->contains($class));
        $this->assertTrue($class->gameVersions->contains($gameVersion));
    }

    // ==================== isInUse ====================

    #[Test]
    public function usage_relations_cover_every_has_many_relation(): void
    {
        $this->assertEqualsCanonicalizing(
            array_keys(self::usageRelations()),
            (new GameVersion)->usageRelations(),
        );
    }

    #[Test]
    public function it_is_not_in_use_without_related_records(): void
    {
        $this->assertFalse($this->create()->isInUse());
    }

    /**
     * @param  class-string<Model>  $relatedModel
     */
    #[Test]
    #[DataProvider('usageRelations')]
    public function it_is_in_use_when_a_related_record_references_it(string $relation, string $relatedModel): void
    {
        $gameVersion = $this->create();
        $relatedModel::factory()->for($gameVersion)->create();

        $this->assertTrue($gameVersion->isInUse(), "Expected a related [{$relation}] record to mark the game version as in use.");
    }

    #[Test]
    public function it_is_not_in_use_when_only_playable_races_and_classes_are_attached(): void
    {
        $gameVersion = $this->create();
        $gameVersion->playableRaces()->attach(PlayableRace::factory()->create());
        $gameVersion->playableClasses()->attach(PlayableClass::factory()->create());

        $this->assertFalse($gameVersion->isInUse());
    }

    #[Test]
    public function it_is_not_in_use_when_related_records_belong_to_another_game_version(): void
    {
        $gameVersion = $this->create();
        Phase::factory()->for(GameVersion::factory())->create();

        $this->assertFalse($gameVersion->isInUse());
    }

    #[Test]
    public function with_usage_counts_loads_a_count_for_every_usage_relation(): void
    {
        $gameVersion = $this->create();
        Phase::factory()->for($gameVersion)->count(2)->create();

        $counted = GameVersion::query()->withUsageCounts()->findOrFail($gameVersion->id);

        $this->assertSame(2, $counted->phases_count);
        $this->assertSame(0, $counted->items_count);
        $this->assertSame(0, $counted->characters_count);
        $this->assertSame(0, $counted->guild_ranks_count);
    }

    // ==================== isBeingEdited ====================

    #[Test]
    public function it_is_not_being_edited_when_nobody_holds_the_edit_lock(): void
    {
        $this->assertFalse($this->create()->isBeingEdited());
    }

    #[Test]
    public function it_is_being_edited_while_an_officer_holds_the_edit_lock(): void
    {
        $gameVersion = $this->create();
        $gameVersion->acquireEditLock(User::factory()->officer()->create());

        $this->assertTrue($gameVersion->isBeingEdited());
    }

    #[Test]
    public function it_is_no_longer_being_edited_once_the_edit_lock_expires(): void
    {
        $gameVersion = $this->create();
        $gameVersion->acquireEditLock(User::factory()->officer()->create());

        $this->travel(GameVersion::EDIT_LOCK_SECONDS + 1)->seconds();

        $this->assertFalse($gameVersion->isBeingEdited());
    }

    #[Test]
    public function it_keys_the_edit_lock_cache_entries_under_the_game_versions_prefix(): void
    {
        $gameVersion = $this->create();
        $officer = User::factory()->officer()->create();

        $gameVersion->acquireEditLock($officer);

        $this->assertSame($officer->id, Cache::get("game-versions.{$gameVersion->id}.editor"));
        $this->assertTrue(Cache::lock("game-versions.{$gameVersion->id}.editing")->isLocked());
    }

    // ==================== current rosters ====================

    #[Test]
    public function it_owns_its_roster_when_it_is_the_latest_release_sharing_it(): void
    {
        $this->createFetchable(['release_date' => now()->subYear()]);
        $latest = $this->createFetchable(['release_date' => now()->subMonth()]);

        $this->assertTrue($latest->ownsCurrentRoster());
    }

    #[Test]
    public function it_does_not_own_a_roster_superseded_by_a_later_release(): void
    {
        $older = $this->createFetchable(['release_date' => now()->subYear()]);
        $this->createFetchable(['release_date' => now()->subMonth()]);

        $this->assertFalse($older->ownsCurrentRoster());
    }

    #[Test]
    public function it_owns_its_roster_when_a_later_release_is_another_guild(): void
    {
        $older = $this->createFetchable(['release_date' => now()->subYear()]);
        $this->createFetchable(['release_date' => now()->subMonth(), 'guild_name' => 'Another Guild']);

        $this->assertTrue($older->ownsCurrentRoster());
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    #[DataProvider('unfetchableRosterAttributes')]
    #[Group('edge-case')]
    #[Test]
    public function it_does_not_own_a_roster_it_cannot_fetch(array $attributes): void
    {
        $this->assertFalse($this->createFetchable($attributes)->ownsCurrentRoster());
    }

    #[Test]
    public function default_roster_is_the_most_recently_released_current_roster(): void
    {
        GameVersion::factory()->fetchableRoster()->create(['release_date' => Carbon::now()->subYear(), 'guild_name' => 'Older Guild']);
        $latest = GameVersion::factory()->fetchableRoster()->create(['release_date' => Carbon::now()->subDay(), 'guild_name' => 'Latest Guild']);

        $this->assertTrue($latest->is(GameVersion::defaultRoster()));
    }

    #[Test]
    public function default_roster_ignores_unreleased_versions(): void
    {
        GameVersion::factory()->fetchableRoster()->create(['release_date' => Carbon::now()->addMonth()]);

        $this->assertNull(GameVersion::defaultRoster());
    }

    #[Test]
    public function default_roster_is_null_when_no_versions_exist(): void
    {
        $this->assertNull(GameVersion::defaultRoster());
    }

    // ==================== helpers ====================

    /**
     * @return array<string, array{string, class-string<Model>}>
     */
    public static function usageRelations(): array
    {
        return [
            'phases' => ['phases', Phase::class],
            'items' => ['items', Item::class],
            'characters' => ['characters', Character::class],
            'guildRanks' => ['guildRanks', GuildRank::class],
        ];
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function unfetchableRosterAttributes(): array
    {
        return [
            'no Blizzard namespace' => [['blizzard_namespace' => null]],
            'not released yet' => [['release_date' => '2999-01-01']],
            'no realm' => [['realm' => null]],
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createFetchable(array $overrides = []): GameVersion
    {
        return $this->create([
            'realm' => 'Thunderstrike',
            'guild_name' => 'Regrowth',
            'blizzard_namespace' => BlizzardNamespace::ANNIVERSARY,
            'release_date' => now()->subMonth(),
            ...$overrides,
        ]);
    }
}
