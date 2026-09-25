<?php

namespace Tests\Feature\Actions\GameVersion;

use App\Actions\GameVersion\BuildGameVersionRelationships;
use App\Models\GameVersion;
use App\Models\Phase;
use App\Models\PlayableClass;
use App\Models\PlayableRace;
use App\Models\Raid;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

        $this->assertCount(2, $relationships['playable_races']['options']);
        $this->assertSame([$linkedRace->id], $relationships['playable_races']['selected_ids']);
        $this->assertCount(1, $relationships['playable_classes']['options']);
        $this->assertSame([$linkedClass->id], $relationships['playable_classes']['selected_ids']);
    }

    #[Group('happy-path')]
    #[Test]
    public function it_names_the_game_version_that_owns_each_record(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $otherGameVersion = GameVersion::factory()->create(['title' => 'Era']);
        $linked = Phase::factory()->for($gameVersion)->create(['number' => '1.0']);
        Phase::factory()->for($otherGameVersion)->create(['number' => '2.0']);
        Phase::factory()->create(['number' => '3.0']);

        $relationships = $this->build($gameVersion);

        $this->assertSame([$linked->id], $relationships['phases']['selected_ids']);
        $this->assertSame('Phase 1', $relationships['phases']['options'][0]['label']);
        $this->assertSame(['id' => $otherGameVersion->id, 'title' => 'Era'], $relationships['phases']['options'][1]['game_version']);
        $this->assertNull($relationships['phases']['options'][2]['game_version']);
        $this->assertSame(['playable_races', 'playable_classes', 'phases'], array_keys($relationships));
    }

    #[Test]
    public function it_nests_each_phases_raids_under_it_ordered_by_name(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $phaseTwo = Phase::factory()->create(['number' => '2.0']);
        $phaseOne = Phase::factory()->create(['number' => '1.0']);
        Raid::factory()->for($phaseTwo)->create(['name' => 'Serpentshrine Cavern']);
        Raid::factory()->for($phaseOne)->create(['name' => "Magtheridon's Lair"]);
        Raid::factory()->for($phaseOne)->create(['name' => "Gruul's Lair"]);

        $relationships = $this->build($gameVersion);

        $phaseOneOption = collect($relationships['phases']['options'])->firstWhere('id', $phaseOne->id);
        $phaseTwoOption = collect($relationships['phases']['options'])->firstWhere('id', $phaseTwo->id);

        $this->assertSame(["Gruul's Lair", "Magtheridon's Lair"], array_column($phaseOneOption['raids'], 'name'));
        $this->assertSame(['Serpentshrine Cavern'], array_column($phaseTwoOption['raids'], 'name'));
    }

    #[Test]
    public function it_lists_no_raids_for_a_phase_that_has_none(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $phase = Phase::factory()->create();

        $relationships = $this->build($gameVersion);

        $option = collect($relationships['phases']['options'])->firstWhere('id', $phase->id);
        $this->assertSame([], $option['raids']);
    }

    /**
     * @return array<string, array{options: list<array<string, mixed>>, selected_ids: list<int>}>
     */
    private function build(GameVersion $gameVersion): array
    {
        return BuildGameVersionRelationships::run($gameVersion);
    }
}
