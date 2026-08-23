<?php

namespace Tests\Unit\Services\LootPriorities;

use App\Models\Item;
use App\Models\LootPriority;
use App\Models\Phase;
use App\Models\PlayableClass;
use App\Models\Raid;
use App\Services\LootPriorities\HighestPriorityStats;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('loot')]
class HighestPriorityStatsTest extends TestCase
{
    use RefreshDatabase;

    protected function service(): HighestPriorityStats
    {
        return app(HighestPriorityStats::class);
    }

    protected function itemInPhase(Phase $phase): Item
    {
        $raid = Raid::factory()->create(['phase_id' => $phase->id]);
        $item = Item::factory()->create();
        $item->raids()->attach($raid->id);

        return $item;
    }

    // ==================== phases() ====================

    #[Test]
    public function phases_only_includes_phases_with_a_non_meme_priority_on_an_item(): void
    {
        $phaseWithPriority = Phase::factory()->create(['number' => 1]);
        $phaseWithoutPriority = Phase::factory()->create(['number' => 2]);

        $item = $this->itemInPhase($phaseWithPriority);
        $priority = LootPriority::factory()->role()->create();
        $item->priorities()->attach($priority->id, ['weight' => 0]);

        $this->itemInPhase($phaseWithoutPriority);

        $phases = $this->service()->phases();

        $this->assertCount(1, $phases);
        $this->assertSame($phaseWithPriority->id, $phases->first()->id);
    }

    #[Test]
    public function phases_excludes_a_phase_whose_only_priority_is_meme(): void
    {
        $phase = Phase::factory()->create(['number' => 1]);
        $item = $this->itemInPhase($phase);
        $meme = LootPriority::factory()->meme()->create();
        $item->priorities()->attach($meme->id, ['weight' => 0]);

        $phases = $this->service()->phases();

        $this->assertCount(0, $phases);
    }

    #[Test]
    public function phases_are_ordered_by_number_ascending(): void
    {
        $phaseThree = Phase::factory()->create(['number' => 3]);
        $phaseOne = Phase::factory()->create(['number' => 1]);
        $phaseThreeFive = Phase::factory()->create(['number' => 3.5]);

        foreach ([$phaseThree, $phaseOne, $phaseThreeFive] as $phase) {
            $item = $this->itemInPhase($phase);
            $priority = LootPriority::factory()->role()->create();
            $item->priorities()->attach($priority->id, ['weight' => 0]);
        }

        $phases = $this->service()->phases();

        $this->assertSame(
            [$phaseOne->id, $phaseThree->id, $phaseThreeFive->id],
            $phases->pluck('id')->all()
        );
    }

    // ==================== table(): aggregation ====================

    #[Test]
    public function counts_an_item_only_for_its_lowest_weight_priority(): void
    {
        $phase = Phase::factory()->create(['number' => 1]);
        $item = $this->itemInPhase($phase);
        $tank = LootPriority::factory()->role()->create(['title' => 'Tank']);
        $healer = LootPriority::factory()->role()->create(['title' => 'Healer']);
        $item->priorities()->attach($tank->id, ['weight' => 0]);
        $item->priorities()->attach($healer->id, ['weight' => 1]);

        $rows = $this->service()->table();

        $tankRow = collect($rows)->firstWhere('id', $tank->id);
        $healerRow = collect($rows)->firstWhere('id', $healer->id);

        $this->assertSame(1, $tankRow['counts'][$phase->id]);
        $this->assertSame(0, $healerRow['counts'][$phase->id]);
    }

    #[Test]
    public function counts_every_priority_tied_at_the_lowest_weight(): void
    {
        $phase = Phase::factory()->create(['number' => 1]);
        $item = $this->itemInPhase($phase);
        $tank = LootPriority::factory()->role()->create(['title' => 'Tank']);
        $healer = LootPriority::factory()->role()->create(['title' => 'Healer']);
        $item->priorities()->attach($tank->id, ['weight' => 0]);
        $item->priorities()->attach($healer->id, ['weight' => 0]);

        $rows = $this->service()->table();

        $tankRow = collect($rows)->firstWhere('id', $tank->id);
        $healerRow = collect($rows)->firstWhere('id', $healer->id);

        $this->assertSame(1, $tankRow['counts'][$phase->id]);
        $this->assertSame(1, $healerRow['counts'][$phase->id]);
    }

    #[Test]
    public function counts_a_sole_priority_as_tier_one_regardless_of_weight(): void
    {
        $phase = Phase::factory()->create(['number' => 1]);
        $item = $this->itemInPhase($phase);
        $tank = LootPriority::factory()->role()->create(['title' => 'Tank']);
        $item->priorities()->attach($tank->id, ['weight' => 7]);

        $rows = $this->service()->table();

        $tankRow = collect($rows)->firstWhere('id', $tank->id);

        $this->assertSame(1, $tankRow['counts'][$phase->id]);
    }

    #[Test]
    public function ignores_meme_weights_when_computing_the_minimum(): void
    {
        $phase = Phase::factory()->create(['number' => 1]);
        $item = $this->itemInPhase($phase);
        $meme = LootPriority::factory()->meme()->create();
        $role = LootPriority::factory()->role()->create(['title' => 'Tank']);
        $item->priorities()->attach($meme->id, ['weight' => 0]);
        $item->priorities()->attach($role->id, ['weight' => 5]);

        $rows = $this->service()->table();

        $roleRow = collect($rows)->firstWhere('id', $role->id);

        $this->assertSame(1, $roleRow['counts'][$phase->id]);
    }

    #[Test]
    public function excludes_meme_priorities_from_rows(): void
    {
        $phase = Phase::factory()->create(['number' => 1]);
        $item = $this->itemInPhase($phase);
        $meme = LootPriority::factory()->meme()->create();
        $role = LootPriority::factory()->role()->create();
        $item->priorities()->attach($meme->id, ['weight' => 0]);
        $item->priorities()->attach($role->id, ['weight' => 1]);

        $rows = $this->service()->table();

        $this->assertNull(collect($rows)->firstWhere('id', $meme->id));
    }

    #[Test]
    public function counts_an_item_once_per_phase_it_appears_in(): void
    {
        $phaseOne = Phase::factory()->create(['number' => 1]);
        $phaseTwo = Phase::factory()->create(['number' => 2]);
        $raidOne = Raid::factory()->create(['phase_id' => $phaseOne->id]);
        $raidTwo = Raid::factory()->create(['phase_id' => $phaseTwo->id]);

        $item = Item::factory()->create();
        $item->raids()->attach([$raidOne->id, $raidTwo->id]);

        $tank = LootPriority::factory()->role()->create();
        $item->priorities()->attach($tank->id, ['weight' => 0]);

        $rows = $this->service()->table();

        $tankRow = collect($rows)->firstWhere('id', $tank->id);

        $this->assertSame(1, $tankRow['counts'][$phaseOne->id]);
        $this->assertSame(1, $tankRow['counts'][$phaseTwo->id]);
    }

    #[Test]
    public function does_not_double_count_an_item_in_two_raids_of_the_same_phase(): void
    {
        $phase = Phase::factory()->create(['number' => 1]);
        $raidOne = Raid::factory()->create(['phase_id' => $phase->id]);
        $raidTwo = Raid::factory()->create(['phase_id' => $phase->id]);

        $item = Item::factory()->create();
        $item->raids()->attach([$raidOne->id, $raidTwo->id]);

        $tank = LootPriority::factory()->role()->create();
        $item->priorities()->attach($tank->id, ['weight' => 0]);

        $rows = $this->service()->table();

        $tankRow = collect($rows)->firstWhere('id', $tank->id);

        $this->assertSame(1, $tankRow['counts'][$phase->id]);
    }

    // ==================== table(): structure ====================

    #[Test]
    public function role_rows_render_flat_and_first(): void
    {
        $phase = Phase::factory()->create(['number' => 1]);
        $item = $this->itemInPhase($phase);
        $tank = LootPriority::factory()->role()->create();
        $item->priorities()->attach($tank->id, ['weight' => 0]);

        $rows = $this->service()->table();

        $this->assertSame('priority', $rows[0]['kind']);
        $this->assertSame($tank->id, $rows[0]['id']);
    }

    #[Test]
    public function class_groups_render_after_role_rows(): void
    {
        $phase = Phase::factory()->create(['number' => 1]);
        $item = $this->itemInPhase($phase);
        $playableClass = PlayableClass::factory()->create();

        $tank = LootPriority::factory()->role()->create();
        $classPriority = LootPriority::factory()->classType()->withPlayableClass($playableClass)->create();
        $item->priorities()->attach($tank->id, ['weight' => 0]);
        $item->priorities()->attach($classPriority->id, ['weight' => 1]);

        $rows = $this->service()->table();

        $this->assertSame(['priority', 'class'], collect($rows)->pluck('kind')->all());
    }

    #[Test]
    public function class_type_priority_renders_as_a_child_row(): void
    {
        $phase = Phase::factory()->create(['number' => 1]);
        $item = $this->itemInPhase($phase);
        $playableClass = PlayableClass::factory()->create();
        $classPriority = LootPriority::factory()->classType()->withPlayableClass($playableClass)->create();
        $item->priorities()->attach($classPriority->id, ['weight' => 0]);

        $rows = $this->service()->table();

        $classGroup = collect($rows)->firstWhere('kind', 'class');

        $this->assertNotNull($classGroup);
        $this->assertCount(1, $classGroup['children']);
        $this->assertSame($classPriority->id, $classGroup['children'][0]['id']);
    }

    #[Test]
    public function class_group_children_are_ordered_class_then_spec_then_custom(): void
    {
        $phase = Phase::factory()->create(['number' => 1]);
        $item = $this->itemInPhase($phase);
        $playableClass = PlayableClass::factory()->create();

        $custom = LootPriority::factory()->custom()->withPlayableClass($playableClass)->create(['title' => 'Zzz Custom']);
        $spec = LootPriority::factory()->spec()->withPlayableClass($playableClass)->create(['title' => 'Aaa Spec']);
        $classType = LootPriority::factory()->classType()->withPlayableClass($playableClass)->create(['title' => 'Class']);

        $item->priorities()->attach($custom->id, ['weight' => 0]);
        $item->priorities()->attach($spec->id, ['weight' => 1]);
        $item->priorities()->attach($classType->id, ['weight' => 2]);

        $rows = $this->service()->table();

        $classGroup = collect($rows)->firstWhere('kind', 'class');
        $childIds = collect($classGroup['children'])->pluck('id')->all();

        $this->assertSame([$classType->id, $spec->id, $custom->id], $childIds);
    }

    #[Test]
    public function class_group_counts_are_the_sum_of_child_counts(): void
    {
        $phase = Phase::factory()->create(['number' => 1]);
        $item = $this->itemInPhase($phase);
        $playableClass = PlayableClass::factory()->create();

        $classType = LootPriority::factory()->classType()->withPlayableClass($playableClass)->create();
        $spec = LootPriority::factory()->spec()->withPlayableClass($playableClass)->create();

        $item->priorities()->attach($classType->id, ['weight' => 0]);
        $item->priorities()->attach($spec->id, ['weight' => 0]);

        $rows = $this->service()->table();

        $classGroup = collect($rows)->firstWhere('kind', 'class');

        $this->assertSame(2, $classGroup['counts'][$phase->id]);
    }

    #[Test]
    public function only_classes_with_priorities_render_a_group(): void
    {
        PlayableClass::factory()->create();

        $rows = $this->service()->table();

        $this->assertCount(0, collect($rows)->where('kind', 'class'));
    }

    #[Test]
    public function renders_an_empty_table_when_no_priorities_have_items(): void
    {
        $rows = $this->service()->table();

        $this->assertSame([], $rows);
    }
}
