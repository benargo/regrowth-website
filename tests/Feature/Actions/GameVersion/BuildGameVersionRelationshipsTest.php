<?php

namespace Tests\Feature\Actions\GameVersion;

use App\Actions\GameVersion\BuildGameVersionRelationships;
use App\Models\Character;
use App\Models\GameVersion;
use App\Models\GuildRank;
use App\Models\Phase;
use App\Models\PlayableClass;
use App\Models\PlayableRace;
use App\Models\Raid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Resources\Json\ResourceCollection;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('platform')]
class BuildGameVersionRelationshipsTest extends TestCase
{
    use RefreshDatabase;

    #[Group('happy-path')]
    #[Test]
    public function it_lists_every_race_and_class_and_selects_the_linked_ones(): void
    {
        $gameVersion = GameVersion::factory()->create();
        [$linkedRace] = PlayableRace::factory()->count(2)->create();
        $linkedClass = PlayableClass::factory()->create();
        $gameVersion->playableRaces()->attach($linkedRace);
        $gameVersion->playableClasses()->attach($linkedClass);

        $relationships = $this->build($gameVersion);

        $this->assertCount(2, $relationships['playable_races']['data']);
        $this->assertSame([$linkedRace->id], $relationships['playable_races']['selected_ids']);
        $this->assertCount(1, $relationships['playable_classes']['data']);
        $this->assertSame([$linkedClass->id], $relationships['playable_classes']['selected_ids']);
    }

    #[Group('happy-path')]
    #[Test]
    public function it_names_the_game_version_that_owns_each_record(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $otherGameVersion = GameVersion::factory()->create(['title' => 'Era']);
        $linked = Phase::factory()->for($gameVersion)->create(['number' => '1.0', 'start_date' => '2026-01-01 00:00:00']);
        Phase::factory()->for($otherGameVersion)->create(['number' => '2.0', 'start_date' => '2026-02-01 00:00:00']);
        Phase::factory()->create(['number' => '3.0', 'start_date' => '2026-03-01 00:00:00']);

        $relationships = $this->build($gameVersion);

        $this->assertSame([$linked->id], $relationships['phases']['selected_ids']);
        $this->assertSame('Phase 1', $relationships['phases']['data'][0]['name']);
        $this->assertSame($otherGameVersion->id, $relationships['phases']['data'][1]['game_version']['id']);
        $this->assertSame('Era', $relationships['phases']['data'][1]['game_version']['title']);
        $this->assertNull($relationships['phases']['data'][2]['game_version']);
        $this->assertSame(['playable_races', 'playable_classes', 'phases', 'unscheduled_phases', 'guild_ranks'], array_keys($relationships));
    }

    #[Test]
    public function it_lists_the_phases_in_release_order(): void
    {
        $unscheduled = Phase::factory()->unscheduled()->create(['number' => '4.0']);
        $later = Phase::factory()->create(['number' => '1.0', 'start_date' => '2026-05-15 00:00:00']);
        $earlier = Phase::factory()->create(['number' => '3.0', 'start_date' => '2026-01-01 00:00:00']);

        $relationships = $this->build(GameVersion::factory()->create());

        $this->assertSame(
            [$earlier->id, $later->id, $unscheduled->id],
            array_column($relationships['phases']['data'], 'id'),
        );
    }

    #[Test]
    public function it_nests_each_phases_raids_under_it_ordered_by_name(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $phaseTwo = Phase::factory()->create(['number' => '2.0']);
        $phaseOne = Phase::factory()->create(['number' => '1.0']);
        Raid::factory()->for($phaseTwo)->heroic()->create(['name' => 'Serpentshrine Cavern']);
        Raid::factory()->for($phaseOne)->create(['name' => "Magtheridon's Lair"]);
        Raid::factory()->for($phaseOne)->create(['name' => "Gruul's Lair"]);

        $relationships = $this->build($gameVersion);

        $phaseOneOption = collect($relationships['phases']['data'])->firstWhere('id', $phaseOne->id);
        $phaseTwoOption = collect($relationships['phases']['data'])->firstWhere('id', $phaseTwo->id);

        $this->assertSame(["Gruul's Lair", "Magtheridon's Lair"], array_column($phaseOneOption['raids'], 'name'));
        $this->assertSame(['Serpentshrine Cavern'], array_column($phaseTwoOption['raids'], 'name'));
        $this->assertSame('Heroic', $phaseTwoOption['raids'][0]['difficulty']);
    }

    #[Test]
    public function it_lists_no_raids_for_a_phase_that_has_none(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $phase = Phase::factory()->create();

        $relationships = $this->build($gameVersion);

        $option = collect($relationships['phases']['data'])->firstWhere('id', $phase->id);
        $this->assertSame([], $option['raids']);
    }

    #[Group('happy-path')]
    #[Test]
    public function it_lists_the_game_versions_own_guild_ranks_in_rank_order_with_character_counts(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $raider = GuildRank::factory()->for($gameVersion)->create(['sort_order' => 1, 'name' => 'Raider']);
        GuildRank::factory()->for($gameVersion)->doesNotCountAttendance()->create(['sort_order' => 0, 'name' => 'Guild Master']);
        GuildRank::factory()->for(GameVersion::factory())->create(['sort_order' => 0, 'name' => 'Elsewhere']);
        Character::factory()->count(2)->create(['rank_id' => $raider->id]);

        $ranks = $this->build($gameVersion)['guild_ranks']['data'];

        $this->assertSame(['Guild Master', 'Raider'], array_column($ranks, 'name'));
        $this->assertSame([0, 1], array_column($ranks, 'sort_order'));
        $this->assertSame([false, true], array_column($ranks, 'count_attendance'));
        $this->assertSame([0, 2], array_column($ranks, 'characters_count'));
        $this->assertSame($raider->id, $ranks[1]['id']);
    }

    #[Test]
    public function it_lists_the_game_versions_unscheduled_phases_in_sort_order(): void
    {
        $gameVersion = GameVersion::factory()->create();
        [$first, $second] = Phase::factory()->for($gameVersion)->unscheduled()->count(2)->create();
        $first->update(['sort_order' => 2]);
        $second->update(['sort_order' => 1]);
        Phase::factory()->for($gameVersion)->started()->create();
        Phase::factory()->for(GameVersion::factory())->unscheduled()->create();

        $relationships = $this->build($gameVersion);

        $this->assertSame([$second->id, $first->id], array_column($relationships['unscheduled_phases']['data'], 'id'));
    }

    /**
     * Serialise each resource collection the way Inertia does, so the
     * additional selected_ids sit alongside the wrapped data.
     *
     * @return array<string, array{data: list<array<string, mixed>>, selected_ids?: list<int>}>
     */
    private function build(GameVersion $gameVersion): array
    {
        return collect(BuildGameVersionRelationships::run($gameVersion))
            ->map(fn (ResourceCollection $collection): array => $collection->response()->getData(true))
            ->all();
    }
}
