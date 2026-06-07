<?php

namespace Tests\Unit\Models;

use App\Events\CharacterDeleted;
use App\Events\CharacterUpdated;
use App\Models\Character;
use App\Models\GuildRank;
use App\Models\PlannedAbsence;
use App\Models\PlayableClass;
use App\Models\PlayableRace;
use App\Models\PlayableSpecialization;
use App\Models\Raids\Report;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use Spatie\MediaLibrary\HasMedia;
use Tests\Support\ModelTestCase;

class CharacterTest extends ModelTestCase
{
    protected function modelClass(): string
    {
        return Character::class;
    }

    #[Test]
    public function it_uses_characters_table(): void
    {
        $model = new Character;

        $this->assertSame('characters', $model->getTable());
    }

    #[Test]
    public function it_has_expected_fillable_attributes(): void
    {
        $model = new Character;

        $this->assertFillable($model, [
            'id',
            'name',
            'level',
            'rank_id',
            'playable_class_id',
            'playable_race_id',
            'is_main',
            'is_loot_councillor',
        ]);
    }

    #[Test]
    public function it_has_expected_casts(): void
    {
        $model = new Character;

        $this->assertCasts($model, [
            'is_main' => 'boolean',
            'is_loot_councillor' => 'boolean',
        ]);
    }

    #[Test]
    public function it_dispatches_events_on_updated_and_deleted(): void
    {
        $model = new Character;

        $this->assertSame([
            'updated' => CharacterUpdated::class,
            'deleted' => CharacterDeleted::class,
        ], $model->dispatchesEvents());
    }

    #[Test]
    public function it_uses_auto_incrementing_primary_key(): void
    {
        $model = new Character;

        $this->assertSame('id', $model->getKeyName());
        $this->assertTrue($model->getIncrementing());
    }

    #[Test]
    public function it_can_create_a_character(): void
    {
        $character = $this->create([
            'name' => 'Thrall',
        ]);

        $this->assertTableHas([
            'name' => 'Thrall',
        ]);
        $this->assertModelExists($character);
    }

    #[Test]
    public function it_has_timestamps(): void
    {
        $character = $this->create();

        $this->assertNotNull($character->created_at);
        $this->assertNotNull($character->updated_at);
    }

    // is_loot_councillor

    #[Test]
    public function it_can_be_created_as_loot_councillor(): void
    {
        $character = $this->factory()->lootCouncillor()->create();

        $this->assertTrue($character->is_loot_councillor);
    }

    #[Test]
    public function it_is_not_a_loot_councillor_by_default(): void
    {
        $character = $this->create();

        $this->assertFalse($character->is_loot_councillor);
    }

    // is_main

    #[Test]
    public function it_can_be_created_as_main(): void
    {
        $character = $this->factory()->main()->create();

        $this->assertTrue($character->is_main);
    }

    // slug

    #[Test]
    public function slug_returns_url_safe_version_of_name(): void
    {
        $character = $this->factory()->make(['name' => 'Death Knight Bob']);

        $this->assertSame('death-knight-bob', $character->slug);
    }

    #[Test]
    public function slug_strips_apostrophes_and_special_characters(): void
    {
        $character = $this->factory()->make(['name' => "Kael'thas"]);

        $this->assertSame('kaelthas', $character->slug);
    }

    // linked_characters

    #[Test]
    public function linked_characters_returns_belongs_to_many_relationship(): void
    {
        $character = new Character;

        $this->assertInstanceOf(BelongsToMany::class, $character->linkedCharacters());
    }

    #[Test]
    public function it_can_link_characters_together(): void
    {
        $mainCharacter = $this->factory()->main()->create(['name' => 'MainChar']);
        $altCharacter = $this->create(['name' => 'AltChar']);

        $altCharacter->linkedCharacters()->attach($mainCharacter->id);

        $this->assertCount(1, $altCharacter->linkedCharacters);
        $this->assertSame($mainCharacter->id, $altCharacter->linkedCharacters->first()->id);
    }

    #[Test]
    public function linked_characters_returns_empty_collection_when_no_links_exist(): void
    {
        $character = $this->create();

        $this->assertCount(0, $character->linkedCharacters);
    }

    #[Test]
    public function deleting_character_cascades_to_character_links(): void
    {
        Event::fake([CharacterDeleted::class]);

        $mainCharacter = $this->factory()->main()->create(['name' => 'MainChar']);
        $altCharacter = $this->create(['name' => 'AltChar']);

        $altCharacter->linkedCharacters()->attach($mainCharacter->id);

        $this->assertDatabaseHas('character_links', [
            'character_id' => $mainCharacter->id,
            'linked_character_id' => $altCharacter->id,
        ]);

        $altCharacter->delete();

        $this->assertDatabaseMissing('character_links', [
            'linked_character_id' => $altCharacter->id,
        ]);
    }

    // main_character

    #[Test]
    public function main_character_returns_linked_character_with_is_main_true(): void
    {
        $mainCharacter = $this->factory()->main()->create(['name' => 'MainChar']);
        $altCharacter = $this->create(['name' => 'AltChar']);

        $altCharacter->linkedCharacters()->attach($mainCharacter->id);

        $this->assertNotNull($altCharacter->mainCharacter);
        $this->assertSame($mainCharacter->id, $altCharacter->mainCharacter->id);
        $this->assertTrue($altCharacter->mainCharacter->is_main);
    }

    #[Test]
    public function main_character_returns_null_when_no_linked_characters_exist(): void
    {
        $character = $this->create();

        $this->assertNull($character->mainCharacter);
    }

    #[Test]
    public function main_character_returns_null_when_no_linked_character_is_main(): void
    {
        $linkedCharacter = $this->create(['name' => 'LinkedChar', 'is_main' => false]);
        $character = $this->create(['name' => 'Character']);

        $character->linkedCharacters()->attach($linkedCharacter->id);

        $this->assertNull($character->mainCharacter);
    }

    // planned_absences

    #[Test]
    public function planned_absences_returns_has_many_relationship(): void
    {
        $character = new Character;

        $this->assertInstanceOf(HasMany::class, $character->plannedAbsences());
    }

    #[Test]
    public function it_can_have_planned_absences(): void
    {
        $character = $this->create();
        PlannedAbsence::factory()->count(2)->create(['character_id' => $character->id]);

        $this->assertCount(2, $character->plannedAbsences);
        $this->assertContainsOnlyInstancesOf(PlannedAbsence::class, $character->plannedAbsences);
    }

    #[Test]
    public function planned_absences_returns_empty_collection_when_none_exist(): void
    {
        $character = $this->create();

        $this->assertCount(0, $character->plannedAbsences);
    }

    // playable_class

    #[Test]
    public function playable_class_returns_belongs_to_relationship(): void
    {
        $character = new Character;

        $this->assertInstanceOf(BelongsTo::class, $character->playableClass());
    }

    #[Test]
    public function it_can_be_created_with_playable_class(): void
    {
        $playableClass = PlayableClass::factory()->create();
        $character = $this->factory()->withPlayableClass($playableClass)->create();

        $this->assertSame($playableClass->id, $character->playable_class_id);
        $this->assertInstanceOf(PlayableClass::class, $character->playableClass);
    }

    #[Test]
    public function playable_class_is_null_by_default(): void
    {
        $character = $this->create();

        $this->assertNull($character->playable_class_id);
        $this->assertNull($character->playableClass);
    }

    #[Test]
    public function playable_class_is_set_to_null_when_playable_class_is_deleted(): void
    {
        $playableClass = PlayableClass::factory()->create();
        $character = $this->factory()->withPlayableClass($playableClass)->create();

        $playableClass->delete();

        $character->refresh();
        $this->assertNull($character->playable_class_id);
    }

    // playable_race

    #[Test]
    public function playable_race_returns_belongs_to_relationship(): void
    {
        $character = new Character;

        $this->assertInstanceOf(BelongsTo::class, $character->playableRace());
    }

    #[Test]
    public function it_can_be_created_with_playable_race(): void
    {
        $playableRace = PlayableRace::factory()->create();
        $character = $this->factory()->withPlayableRace($playableRace)->create();

        $this->assertSame($playableRace->id, $character->playable_race_id);
        $this->assertInstanceOf(PlayableRace::class, $character->playableRace);
    }

    #[Test]
    public function playable_race_is_null_by_default(): void
    {
        $character = $this->create();

        $this->assertNull($character->playable_race_id);
        $this->assertNull($character->playableRace);
    }

    #[Test]
    public function playable_race_is_set_to_null_when_playable_race_is_deleted(): void
    {
        $playableRace = PlayableRace::factory()->create();
        $character = $this->factory()->withPlayableRace($playableRace)->create();

        $playableRace->delete();

        $character->refresh();
        $this->assertNull($character->playable_race_id);
    }

    // prunable

    #[Test]
    public function prunable_returns_builder_instance(): void
    {
        $character = new Character;

        $this->assertInstanceOf(Builder::class, $character->prunable());
    }

    #[Test]
    public function prunable_includes_characters_updated_more_than_14_days_ago(): void
    {
        $prunableCharacter = $this->create([
            'name' => 'OldCharacter',
            'updated_at' => now()->subDays(15),
        ]);

        $prunableIds = (new Character)->prunable()->pluck('id')->toArray();

        $this->assertContains($prunableCharacter->id, $prunableIds);
    }

    #[Test]
    public function prunable_excludes_characters_updated_within_14_days(): void
    {
        $recentCharacter = $this->create([
            'name' => 'RecentCharacter',
            'updated_at' => now()->subDays(13),
        ]);

        $prunableIds = (new Character)->prunable()->pluck('id')->toArray();

        $this->assertNotContains($recentCharacter->id, $prunableIds);
    }

    #[Test]
    public function prunable_includes_characters_updated_exactly_14_days_ago(): void
    {
        $boundaryCharacter = $this->create([
            'name' => 'BoundaryCharacter',
            'updated_at' => now()->subDays(14),
        ]);

        $prunableIds = (new Character)->prunable()->pluck('id')->toArray();

        $this->assertContains($boundaryCharacter->id, $prunableIds);
    }

    #[Test]
    public function prunable_returns_empty_when_all_characters_are_recent(): void
    {
        $this->create(['name' => 'Recent1', 'updated_at' => now()->subDays(1)]);
        $this->create(['name' => 'Recent2', 'updated_at' => now()->subDays(10)]);

        $this->assertCount(0, (new Character)->prunable()->get());
    }

    // specializations

    #[Test]
    public function specializations_returns_belongs_to_many_relationship(): void
    {
        $character = new Character;

        $this->assertInstanceOf(BelongsToMany::class, $character->specializations());
    }

    #[Test]
    public function specializations_returns_empty_collection_by_default(): void
    {
        $character = $this->create();

        $this->assertCount(0, $character->specializations);
    }

    #[Test]
    public function it_can_attach_specializations_via_pivot(): void
    {
        $character = $this->create();
        $specialization = PlayableSpecialization::factory()->create();

        $character->specializations()->attach($specialization->id);

        $this->assertCount(1, $character->fresh()->specializations);
        $this->assertTrue($character->fresh()->specializations->first()->is($specialization));
    }

    #[Test]
    public function deleting_specialization_removes_it_from_characters_specializations(): void
    {
        $character = $this->create();
        $specialization = PlayableSpecialization::factory()->create();
        $character->specializations()->attach($specialization->id);

        $specialization->delete();

        $this->assertDatabaseMissing('pivot_character_specializations', [
            'character_id' => $character->id,
            'playable_specialization_id' => $specialization->id,
        ]);
    }

    // rank

    #[Test]
    public function it_can_be_created_with_rank(): void
    {
        $character = $this->factory()->withRank()->create();

        $this->assertNotNull($character->rank_id);
        $this->assertInstanceOf(GuildRank::class, $character->rank);
    }

    #[Test]
    public function rank_returns_belongs_to_relationship(): void
    {
        $character = new Character;

        $this->assertInstanceOf(BelongsTo::class, $character->rank());
    }

    #[Test]
    public function rank_returns_associated_guild_rank(): void
    {
        $rank = GuildRank::factory()->create(['position' => 0, 'name' => 'Guild Master']);
        $character = $this->create(['rank_id' => $rank->id]);

        $this->assertInstanceOf(GuildRank::class, $character->rank);
        $this->assertSame($rank->id, $character->rank->id);
    }

    #[Test]
    public function rank_returns_null_when_no_rank_assigned(): void
    {
        $character = $this->create(['rank_id' => null]);

        $this->assertNull($character->rank);
    }

    #[Test]
    public function rank_is_set_to_null_when_guild_rank_is_deleted(): void
    {
        $rank = GuildRank::factory()->create(['position' => 0, 'name' => 'Officer']);
        $character = $this->create(['rank_id' => $rank->id]);

        $this->assertSame($rank->id, $character->rank_id);

        $rank->delete();

        $character->refresh();
        $this->assertNull($character->rank_id);
    }

    // HasMedia / character_portrait

    #[Test]
    public function it_implements_has_media_interface(): void
    {
        $character = new Character;

        $this->assertInstanceOf(HasMedia::class, $character);
    }

    #[Test]
    public function it_registers_character_portrait_collection(): void
    {
        $character = $this->create();

        $collections = $character->getRegisteredMediaCollections();
        $names = array_map(fn ($c) => $c->name, $collections->all());

        $this->assertContains(Character::MEDIA_COLLECTION, $names);
    }

    #[Test]
    public function character_portrait_collection_is_single_file(): void
    {
        $character = $this->create();

        $collection = $character->getRegisteredMediaCollections()
            ->firstWhere('name', Character::MEDIA_COLLECTION);

        $this->assertTrue($collection->singleFile);
    }

    // warcraft_logs_reports

    #[Test]
    public function warcraft_logs_reports_returns_belongs_to_many_relationship(): void
    {
        $character = new Character;

        $this->assertInstanceOf(BelongsToMany::class, $character->warcraftLogsReports());
    }

    #[Test]
    public function it_can_attach_warcraft_logs_reports(): void
    {
        $character = $this->create();
        $report = Report::factory()->create();

        $character->warcraftLogsReports()->attach($report->id);

        $this->assertCount(1, $character->warcraftLogsReports);
        $this->assertSame($report->code, $character->warcraftLogsReports->first()->code);
    }

    #[Test]
    public function warcraft_logs_reports_returns_empty_collection_when_none_attached(): void
    {
        $character = $this->create();

        $this->assertCount(0, $character->warcraftLogsReports);
    }
}
