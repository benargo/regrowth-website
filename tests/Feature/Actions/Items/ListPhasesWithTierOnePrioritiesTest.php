<?php

namespace Tests\Feature\Actions\Items;

use App\Actions\Items\ListPhasesWithTierOnePriorities;
use App\Models\GameVersion;
use App\Models\Item;
use App\Models\LootPriority;
use App\Models\Phase;
use App\Models\Raid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('loot')]
class ListPhasesWithTierOnePrioritiesTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_only_includes_phases_with_a_non_meme_priority_on_an_item(): void
    {
        $phaseWithPriority = Phase::factory()->create(['number' => 1]);
        $phaseWithoutPriority = Phase::factory()->create(['number' => 2]);

        $item = $this->itemInPhase($phaseWithPriority);
        $priority = LootPriority::factory()->role()->create();
        $item->priorities()->attach($priority->id, ['weight' => 0]);

        $this->itemInPhase($phaseWithoutPriority);

        $phases = ListPhasesWithTierOnePriorities::run(null);

        $this->assertCount(1, $phases);
        $this->assertSame($phaseWithPriority->id, $phases->first()->id);
    }

    #[Test]
    public function it_excludes_a_phase_whose_only_priority_is_meme(): void
    {
        $phase = Phase::factory()->create(['number' => 1]);
        $item = $this->itemInPhase($phase);
        $meme = LootPriority::factory()->meme()->create();
        $item->priorities()->attach($meme->id, ['weight' => 0]);

        $phases = ListPhasesWithTierOnePriorities::run(null);

        $this->assertCount(0, $phases);
    }

    #[Test]
    public function it_lists_phases_in_release_order(): void
    {
        $unscheduled = Phase::factory()->unscheduled()->create(['number' => 4]);
        $later = Phase::factory()->create(['number' => 1, 'start_date' => '2026-05-15 00:00:00']);
        $earlier = Phase::factory()->create(['number' => 3, 'start_date' => '2026-01-01 00:00:00']);

        foreach ([$unscheduled, $later, $earlier] as $phase) {
            $item = $this->itemInPhase($phase);
            $priority = LootPriority::factory()->role()->create();
            $item->priorities()->attach($priority->id, ['weight' => 0]);
        }

        $phases = ListPhasesWithTierOnePriorities::run(null);

        $this->assertSame([$earlier->id, $later->id, $unscheduled->id], $phases->pluck('id')->all());
    }

    #[Test]
    public function it_only_includes_the_given_versions_phases(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $phase = Phase::factory()->forGameVersion($gameVersion)->create(['number' => 1]);
        $otherPhase = Phase::factory()->forGameVersion(GameVersion::factory()->create())->create(['number' => 1]);
        $unversionedPhase = Phase::factory()->create(['number' => 1]);
        $priority = LootPriority::factory()->role()->create();

        foreach ([$phase, $otherPhase, $unversionedPhase] as $eachPhase) {
            $this->itemInPhase($eachPhase)->priorities()->attach($priority->id, ['weight' => 0]);
        }

        $phases = ListPhasesWithTierOnePriorities::run($gameVersion);

        $this->assertSame([$phase->id], $phases->pluck('id')->all());
    }

    protected function itemInPhase(Phase $phase): Item
    {
        $raid = Raid::factory()->create(['phase_id' => $phase->id]);
        $item = Item::factory()->create();
        $item->raids()->attach($raid->id);

        return $item;
    }
}
