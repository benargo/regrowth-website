<?php

namespace Tests\Unit\Models;

use App\Casts\AsPlayableClass;
use App\Casts\AsPlayableRace;
use App\Events\CharacterDeleted;
use App\Events\CharacterUpdated;
use App\Models\Character;
use App\Models\GuildRank;
use App\Models\PlannedAbsence;
use App\Models\Raids\Report;
use App\Services\Blizzard\BlizzardService;
use App\Services\Blizzard\MediaService;
use App\Services\Blizzard\ValueObjects\PlayableClass;
use App\Services\Blizzard\ValueObjects\PlayableRace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Event;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
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
            'is_main',
            'is_loot_councillor',
            'reached_level_cap_at',
            'playable_class',
            'playable_race',
        ]);
    }

    #[Test]
    public function it_has_expected_casts(): void
    {
        $model = new Character;

        $this->assertCasts($model, [
            'is_main' => 'boolean',
            'is_loot_councillor' => 'boolean',
            'reached_level_cap_at' => 'datetime',
            'playable_class' => AsPlayableClass::class,
            'playable_race' => AsPlayableRace::class,
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

    /**
     * @return array<string, mixed>
     */
    private function sampleClassApiResponse(int $id = 7, string $name = 'Shaman'): array
    {
        return [
            'id' => $id,
            'name' => $name,
            'gender_name' => ['male' => $name, 'female' => $name],
            'power_type' => [],
            'media' => [],
            'pvp_talent_slots' => [],
            'playable_races' => [],
        ];
    }

    private function mockPlayableClassMediaResolution(int $classId, ?string $iconUrl): void
    {
        $fileDataId = 1000 + $classId;

        $this->mock(BlizzardService::class, function (MockInterface $mock) use ($classId, $fileDataId) {
            $mock->shouldReceive('getPlayableClassMedia')
                ->with($classId)
                ->andReturn([
                    'id' => $classId,
                    'assets' => [
                        [
                            'key' => 'icon',
                            'value' => "https://render.worldofwarcraft.com/eu/icons/56/class_{$classId}.jpg",
                            'file_data_id' => $fileDataId,
                        ],
                    ],
                ]);
        });

        $this->mock(MediaService::class, function (MockInterface $mock) use ($fileDataId, $iconUrl) {
            $mock->shouldReceive('get')->andReturn([$fileDataId => $iconUrl]);
        });
    }

    #[Test]
    public function playable_class_returns_unknown_when_column_is_null(): void
    {
        $this->mock(MediaService::class, function (MockInterface $mock) {
            $mock->shouldReceive('get')
                ->once()
                ->with('inv_misc_questionmark')
                ->andReturn('https://example.com/question.jpg');
        });

        $character = $this->create();

        $playableClass = $character->playable_class;

        $this->assertNull($playableClass['id']);
        $this->assertSame('Unknown Class', $playableClass['name']);
        $this->assertSame('https://example.com/question.jpg', $playableClass['icon_url']);
    }

    #[Test]
    public function playable_class_returns_stored_data_when_set(): void
    {
        $this->mockPlayableClassMediaResolution(1, 'https://cdn.local/warrior.jpg');

        $character = $this->factory()->withPlayableClass(1, 'Warrior')->create();

        $playableClass = $character->fresh()->playable_class;

        $this->assertSame(1, $playableClass['id']);
        $this->assertSame('Warrior', $playableClass['name']);
        $this->assertSame('https://cdn.local/warrior.jpg', $playableClass['icon_url']);
    }

    #[Test]
    public function assigning_playable_class_vo_persists_reduced_shape_via_cast(): void
    {
        $this->mockPlayableClassMediaResolution(7, 'https://cdn.local/shaman.jpg');

        $character = $this->create();
        $character->playable_class = PlayableClass::fromApiResponse($this->sampleClassApiResponse(7, 'Shaman'));
        $character->save();

        $this->assertDatabaseHas('characters', [
            'id' => $character->id,
            'playable_class' => json_encode([
                'id' => 7,
                'name' => 'Shaman',
                'icon_url' => 'https://cdn.local/shaman.jpg',
            ]),
        ]);
    }

    #[Test]
    public function playable_class_setter_accepts_null_and_clears_column(): void
    {
        $this->mockPlayableClassMediaResolution(1, 'https://cdn.local/warrior.jpg');

        $character = $this->factory()->withPlayableClass(1, 'Warrior')->create();
        $character->playable_class = null;
        $character->save();

        $this->assertDatabaseHas('characters', [
            'id' => $character->id,
            'playable_class' => null,
        ]);
    }

    // playable_race

    /**
     * @return array<string, mixed>
     */
    private function sampleRaceApiResponse(int $id = 2, string $name = 'Orc'): array
    {
        return [
            'id' => $id,
            'name' => $name,
            'gender_name' => ['male' => $name, 'female' => $name],
            'faction' => ['type' => 'HORDE', 'name' => 'Horde'],
            'is_selectable' => true,
            'is_allied_race' => false,
            'playable_classes' => [],
            'racial_spells' => [],
        ];
    }

    #[Test]
    public function playable_race_returns_unknown_when_column_is_null(): void
    {
        $character = $this->create();

        $playableRace = $character->playable_race;

        $this->assertNull($playableRace['id']);
        $this->assertSame('Unknown Race', $playableRace['name']);
    }

    #[Test]
    public function playable_race_returns_stored_data_when_set(): void
    {
        $character = $this->factory()->withPlayableRace(2, 'Orc')->create();

        $playableRace = $character->fresh()->playable_race;

        $this->assertSame(2, $playableRace['id']);
        $this->assertSame('Orc', $playableRace['name']);
    }

    #[Test]
    public function assigning_playable_race_vo_persists_reduced_shape_via_cast(): void
    {
        $character = $this->create();
        $character->playable_race = PlayableRace::fromApiResponse($this->sampleRaceApiResponse(2, 'Orc'));
        $character->save();

        $this->assertDatabaseHas('characters', [
            'id' => $character->id,
            'playable_race' => json_encode(['id' => 2, 'name' => 'Orc']),
        ]);
    }

    #[Test]
    public function playable_race_setter_accepts_null_and_clears_column(): void
    {
        $character = $this->factory()->withPlayableRace(2, 'Orc')->create();
        $character->playable_race = null;
        $character->save();

        $this->assertDatabaseHas('characters', [
            'id' => $character->id,
            'playable_race' => null,
        ]);
    }

    // prunable

    #[Test]
    public function prunable_returns_builder_instance(): void
    {
        $this->mockGuildRoster([]);

        $character = new Character;

        $this->assertInstanceOf(Builder::class, $character->prunable());
    }

    #[Test]
    public function prunable_includes_characters_not_in_guild_and_older_than_14_days(): void
    {
        $this->mockGuildRoster([]);

        $prunableCharacter = $this->create([
            'name' => 'OldNonMember',
            'updated_at' => now()->subDays(15),
        ]);

        $character = new Character;
        $prunableIds = $character->prunable()->pluck('id')->toArray();

        $this->assertContains($prunableCharacter->id, $prunableIds);
    }

    #[Test]
    public function prunable_excludes_characters_that_are_guild_members(): void
    {
        $guildMember = $this->create([
            'name' => 'GuildMember',
            'updated_at' => now()->subDays(30),
        ]);

        $this->mockGuildRoster([
            ['character' => ['id' => $guildMember->id, 'name' => 'GuildMember'], 'rank' => 0],
        ]);

        $character = new Character;
        $prunableIds = $character->prunable()->pluck('id')->toArray();

        $this->assertNotContains($guildMember->id, $prunableIds);
    }

    #[Test]
    public function prunable_excludes_characters_updated_within_14_days(): void
    {
        $this->mockGuildRoster([]);

        $recentCharacter = $this->create([
            'name' => 'RecentNonMember',
            'updated_at' => now()->subDays(13),
        ]);

        $character = new Character;
        $prunableIds = $character->prunable()->pluck('id')->toArray();

        $this->assertNotContains($recentCharacter->id, $prunableIds);
    }

    #[Test]
    public function prunable_excludes_characters_updated_exactly_14_days_ago(): void
    {
        $this->mockGuildRoster([]);

        $boundaryCharacter = $this->create([
            'name' => 'BoundaryCharacter',
            'updated_at' => now()->subDays(14),
        ]);

        $character = new Character;
        $prunableIds = $character->prunable()->pluck('id')->toArray();

        $this->assertContains($boundaryCharacter->id, $prunableIds);
    }

    #[Test]
    public function prunable_correctly_filters_mixed_scenarios(): void
    {
        $guildMemberOld = $this->create([
            'name' => 'GuildMemberOld',
            'updated_at' => now()->subDays(30),
        ]);

        $guildMemberRecent = $this->create([
            'name' => 'GuildMemberRecent',
            'updated_at' => now()->subDays(5),
        ]);

        $nonMemberOld = $this->create([
            'name' => 'NonMemberOld',
            'updated_at' => now()->subDays(20),
        ]);

        $nonMemberRecent = $this->create([
            'name' => 'NonMemberRecent',
            'updated_at' => now()->subDays(7),
        ]);

        $this->mockGuildRoster([
            ['character' => ['id' => $guildMemberOld->id, 'name' => 'GuildMemberOld'], 'rank' => 0],
            ['character' => ['id' => $guildMemberRecent->id, 'name' => 'GuildMemberRecent'], 'rank' => 5],
        ]);

        $character = new Character;
        $prunableIds = $character->prunable()->pluck('id')->toArray();

        $this->assertNotContains($guildMemberOld->id, $prunableIds, 'Guild member (old) should not be prunable');
        $this->assertNotContains($guildMemberRecent->id, $prunableIds, 'Guild member (recent) should not be prunable');
        $this->assertContains($nonMemberOld->id, $prunableIds, 'Non-member (old) should be prunable');
        $this->assertNotContains($nonMemberRecent->id, $prunableIds, 'Non-member (recent) should not be prunable');
    }

    #[Test]
    public function prunable_returns_empty_when_all_characters_are_guild_members(): void
    {
        $character1 = $this->create([
            'name' => 'Member1',
            'updated_at' => now()->subDays(30),
        ]);

        $character2 = $this->create([
            'name' => 'Member2',
            'updated_at' => now()->subDays(60),
        ]);

        $this->mockGuildRoster([
            ['character' => ['id' => $character1->id, 'name' => 'Member1'], 'rank' => 0],
            ['character' => ['id' => $character2->id, 'name' => 'Member2'], 'rank' => 1],
        ]);

        $character = new Character;

        $this->assertCount(0, $character->prunable()->get());
    }

    #[Test]
    public function prunable_returns_empty_when_all_characters_are_recent(): void
    {
        $this->mockGuildRoster([]);

        $this->create([
            'name' => 'Recent1',
            'updated_at' => now()->subDays(1),
        ]);

        $this->create([
            'name' => 'Recent2',
            'updated_at' => now()->subDays(10),
        ]);

        $character = new Character;

        $this->assertCount(0, $character->prunable()->get());
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

    // reached_level_cap_at

    // #[Test]
    // public function it_can_be_created_with_reached_level_cap(): void
    // {
    //     $character = $this->factory()->reachedLevelCap()->create();
    //
    //     $this->assertNotNull($character->reached_level_cap_at);
    // }

    #[Test]
    public function reached_level_cap_at_is_null_by_default(): void
    {
        $character = $this->create();

        $this->assertNull($character->reached_level_cap_at);
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

    /**
     * Mock the BlizzardService to return a guild roster with specific members.
     *
     * @param  array<int, array{character: array{id: int, name: string}, rank: int}>  $memberData
     */
    protected function mockGuildRoster(array $memberData): void
    {
        $this->mock(BlizzardService::class, function (MockInterface $mock) use ($memberData) {
            $mock->shouldReceive('getGuildRoster')->andReturn(['members' => $memberData]);
        });
    }
}
