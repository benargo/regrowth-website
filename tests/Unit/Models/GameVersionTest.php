<?php

namespace Tests\Unit\Models;

use App\Casts\AsTheme;
use App\Contracts\Models\DatasetModel;
use App\Enums\Faction;
use App\Enums\Theme;
use App\Http\Integrations\Blizzard\BlizzardNamespace;
use App\Models\Character;
use App\Models\GameVersion;
use App\Models\GuildTag;
use App\Models\Item;
use App\Models\Phase;
use App\Models\PlayableClass;
use App\Models\PlayableRace;
use App\Models\Raid;
use App\Models\Zone;
use App\Policies\DatasetPolicy;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Carbon;
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
            'realm',
            'faction',
            'release_date',
            'theme',
            'blizzard_namespace',
            'warcraftlogs_guild',
            'warcraftlogs_expansion',
        ]);
    }

    #[Test]
    public function it_has_expected_casts(): void
    {
        $model = new GameVersion;

        $this->assertCasts($model, [
            'faction' => Faction::class,
            'release_date' => 'datetime',
            'theme' => AsTheme::class,
            'blizzard_namespace' => BlizzardNamespace::class,
            'warcraftlogs_guild' => 'integer',
            'warcraftlogs_expansion' => 'integer',
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
            'warcraftlogs_expansion' => null,
        ])->fresh();

        $this->assertNull($gameVersion->realm);
        $this->assertNull($gameVersion->faction);
        $this->assertNull($gameVersion->blizzard_namespace);
        $this->assertNull($gameVersion->warcraftlogs_guild);
        $this->assertNull($gameVersion->warcraftlogs_expansion);
    }

    // ==================== casts ====================

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
    public function warcraftlogs_ids_are_cast_to_integers(): void
    {
        $gameVersion = $this->create([
            'warcraftlogs_guild' => '774848',
            'warcraftlogs_expansion' => '1001',
        ])->fresh();

        $this->assertSame(774848, $gameVersion->warcraftlogs_guild);
        $this->assertSame(1001, $gameVersion->warcraftlogs_expansion);
    }

    #[Test]
    public function release_date_round_trips_without_a_timezone_shift(): void
    {
        $releaseDate = Carbon::create(2026, 2, 6, 0, 0, 0, config('app.timezone'));

        $gameVersion = $this->create(['release_date' => $releaseDate])->fresh();

        $this->assertInstanceOf(Carbon::class, $gameVersion->release_date);
        $this->assertTrue($releaseDate->eq($gameVersion->release_date));
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
            GameVersion::USAGE_RELATIONS,
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

    // ==================== helpers ====================

    /**
     * @return array<string, array{string, class-string<Model>}>
     */
    public static function usageRelations(): array
    {
        return [
            'phases' => ['phases', Phase::class],
            'zones' => ['zones', Zone::class],
            'items' => ['items', Item::class],
            'characters' => ['characters', Character::class],
        ];
    }
}
