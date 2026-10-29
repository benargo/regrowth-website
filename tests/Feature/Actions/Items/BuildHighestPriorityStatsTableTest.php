<?php

namespace Tests\Feature\Actions\Items;

use App\Actions\Items\BuildHighestPriorityStatsTable;
use App\Actions\Items\ListPhasesWithTierOnePriorities;
use App\Data\Items\HighestPriorityStatsTableData;
use App\Models\Item;
use App\Models\LootPriority;
use App\Models\Phase;
use App\Models\PlayableClass;
use App\Models\Raid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('loot')]
class BuildHighestPriorityStatsTableTest extends TestCase
{
    use RefreshDatabase;

    // ==================== aggregation ====================

    #[Test]
    public function it_counts_an_item_only_for_its_lowest_weight_priority(): void
    {
        $phase = Phase::factory()->create(['number' => 1]);
        $item = $this->itemInPhase($phase);
        $tank = LootPriority::factory()->role()->create(['title' => 'Tank']);
        $healer = LootPriority::factory()->role()->create(['title' => 'Healer']);
        $item->priorities()->attach($tank->id, ['weight' => 0]);
        $item->priorities()->attach($healer->id, ['weight' => 1]);

        $table = $this->table();

        $tankRow = $table->roleRows->firstWhere('id', $tank->id);
        $healerRow = $table->roleRows->firstWhere('id', $healer->id);

        $this->assertSame(1, $tankRow->counts[$phase->id]);
        $this->assertSame(0, $healerRow->counts[$phase->id]);
    }

    #[Test]
    public function it_counts_every_priority_tied_at_the_lowest_weight(): void
    {
        $phase = Phase::factory()->create(['number' => 1]);
        $item = $this->itemInPhase($phase);
        $tank = LootPriority::factory()->role()->create(['title' => 'Tank']);
        $healer = LootPriority::factory()->role()->create(['title' => 'Healer']);
        $item->priorities()->attach($tank->id, ['weight' => 0]);
        $item->priorities()->attach($healer->id, ['weight' => 0]);

        $table = $this->table();

        $tankRow = $table->roleRows->firstWhere('id', $tank->id);
        $healerRow = $table->roleRows->firstWhere('id', $healer->id);

        $this->assertSame(1, $tankRow->counts[$phase->id]);
        $this->assertSame(1, $healerRow->counts[$phase->id]);
    }

    #[Test]
    public function it_counts_a_sole_priority_as_tier_one_regardless_of_weight(): void
    {
        $phase = Phase::factory()->create(['number' => 1]);
        $item = $this->itemInPhase($phase);
        $tank = LootPriority::factory()->role()->create(['title' => 'Tank']);
        $item->priorities()->attach($tank->id, ['weight' => 7]);

        $table = $this->table();

        $tankRow = $table->roleRows->firstWhere('id', $tank->id);

        $this->assertSame(1, $tankRow->counts[$phase->id]);
    }

    #[Test]
    public function it_ignores_meme_weights_when_computing_the_minimum(): void
    {
        $phase = Phase::factory()->create(['number' => 1]);
        $item = $this->itemInPhase($phase);
        $meme = LootPriority::factory()->meme()->create();
        $role = LootPriority::factory()->role()->create(['title' => 'Tank']);
        $item->priorities()->attach($meme->id, ['weight' => 0]);
        $item->priorities()->attach($role->id, ['weight' => 5]);

        $table = $this->table();

        $roleRow = $table->roleRows->firstWhere('id', $role->id);

        $this->assertSame(1, $roleRow->counts[$phase->id]);
    }

    #[Test]
    public function it_excludes_meme_priorities_from_rows(): void
    {
        $phase = Phase::factory()->create(['number' => 1]);
        $item = $this->itemInPhase($phase);
        $meme = LootPriority::factory()->meme()->create();
        $role = LootPriority::factory()->role()->create();
        $item->priorities()->attach($meme->id, ['weight' => 0]);
        $item->priorities()->attach($role->id, ['weight' => 1]);

        $table = $this->table();

        $this->assertNull($table->roleRows->firstWhere('id', $meme->id));
    }

    #[Test]
    public function it_counts_an_item_once_per_phase_it_appears_in(): void
    {
        $phaseOne = Phase::factory()->create(['number' => 1]);
        $phaseTwo = Phase::factory()->create(['number' => 2]);
        $raidOne = Raid::factory()->create(['phase_id' => $phaseOne->id]);
        $raidTwo = Raid::factory()->create(['phase_id' => $phaseTwo->id]);

        $item = Item::factory()->create();
        $item->raids()->attach([$raidOne->id, $raidTwo->id]);

        $tank = LootPriority::factory()->role()->create();
        $item->priorities()->attach($tank->id, ['weight' => 0]);

        $table = $this->table();

        $tankRow = $table->roleRows->firstWhere('id', $tank->id);

        $this->assertSame(1, $tankRow->counts[$phaseOne->id]);
        $this->assertSame(1, $tankRow->counts[$phaseTwo->id]);
    }

    #[Test]
    public function it_does_not_double_count_an_item_in_two_raids_of_the_same_phase(): void
    {
        $phase = Phase::factory()->create(['number' => 1]);
        $raidOne = Raid::factory()->create(['phase_id' => $phase->id]);
        $raidTwo = Raid::factory()->create(['phase_id' => $phase->id]);

        $item = Item::factory()->create();
        $item->raids()->attach([$raidOne->id, $raidTwo->id]);

        $tank = LootPriority::factory()->role()->create();
        $item->priorities()->attach($tank->id, ['weight' => 0]);

        $table = $this->table();

        $tankRow = $table->roleRows->firstWhere('id', $tank->id);

        $this->assertSame(1, $tankRow->counts[$phase->id]);
    }

    // ==================== structure ====================

    #[Test]
    public function it_renders_role_rows_as_flat_priority_rows(): void
    {
        $phase = Phase::factory()->create(['number' => 1]);
        $item = $this->itemInPhase($phase);
        $tank = LootPriority::factory()->role()->create();
        $item->priorities()->attach($tank->id, ['weight' => 0]);

        $table = $this->table();

        $this->assertSame('priority', $table->roleRows[0]->kind);
        $this->assertSame($tank->id, $table->roleRows[0]->id);
    }

    #[Test]
    public function it_separates_role_rows_from_class_groups(): void
    {
        $phase = Phase::factory()->create(['number' => 1]);
        $item = $this->itemInPhase($phase);
        $playableClass = PlayableClass::factory()->create();

        $tank = LootPriority::factory()->role()->create();
        $classPriority = LootPriority::factory()->classType()->withPlayableClass($playableClass)->create();
        $item->priorities()->attach($tank->id, ['weight' => 0]);
        $item->priorities()->attach($classPriority->id, ['weight' => 1]);

        $table = $this->table();

        $this->assertSame([$tank->id], $table->roleRows->pluck('id')->all());
        $this->assertSame(['class'], $table->classGroups->pluck('kind')->all());
    }

    #[Test]
    public function it_renders_a_class_type_priority_as_a_child_row(): void
    {
        $phase = Phase::factory()->create(['number' => 1]);
        $item = $this->itemInPhase($phase);
        $playableClass = PlayableClass::factory()->create();
        $classPriority = LootPriority::factory()->classType()->withPlayableClass($playableClass)->create();
        $item->priorities()->attach($classPriority->id, ['weight' => 0]);

        $table = $this->table();

        $classGroup = $table->classGroups->first();

        $this->assertNotNull($classGroup);
        $this->assertCount(1, $classGroup->children);
        $this->assertSame($classPriority->id, $classGroup->children[0]->id);
    }

    #[Test]
    public function it_orders_class_group_children_class_then_spec_then_custom(): void
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

        $table = $this->table();

        $classGroup = $table->classGroups->first();
        $childIds = collect($classGroup->children)->pluck('id')->all();

        $this->assertSame([$classType->id, $spec->id, $custom->id], $childIds);
    }

    #[Test]
    public function it_sums_child_counts_into_the_class_group_counts(): void
    {
        $phase = Phase::factory()->create(['number' => 1]);
        $item = $this->itemInPhase($phase);
        $playableClass = PlayableClass::factory()->create();

        $classType = LootPriority::factory()->classType()->withPlayableClass($playableClass)->create();
        $spec = LootPriority::factory()->spec()->withPlayableClass($playableClass)->create();

        $item->priorities()->attach($classType->id, ['weight' => 0]);
        $item->priorities()->attach($spec->id, ['weight' => 0]);

        $table = $this->table();

        $classGroup = $table->classGroups->first();

        $this->assertSame(2, $classGroup->counts[$phase->id]);
    }

    #[Test]
    public function it_only_renders_a_group_for_classes_with_priorities(): void
    {
        PlayableClass::factory()->create();

        $table = $this->table();

        $this->assertCount(0, $table->classGroups);
    }

    #[Test]
    public function it_orders_class_groups_alphabetically_by_class_name(): void
    {
        $phase = Phase::factory()->create(['number' => 1]);
        $item = $this->itemInPhase($phase);

        $warrior = PlayableClass::factory()->create(['name' => 'Warrior']);
        $druid = PlayableClass::factory()->create(['name' => 'Druid']);
        $mage = PlayableClass::factory()->create(['name' => 'Mage']);

        foreach ([$warrior, $druid, $mage] as $playableClass) {
            $priority = LootPriority::factory()->classType()->withPlayableClass($playableClass)->create();
            $item->priorities()->attach($priority->id, ['weight' => 0]);
        }

        $table = $this->table();

        $classGroupNames = $table->classGroups->pluck('title')->all();

        $this->assertSame(['Druid', 'Mage', 'Warrior'], $classGroupNames);
    }

    #[Test]
    public function it_returns_no_rows_when_given_no_phases(): void
    {
        $phase = Phase::factory()->create(['number' => 1]);
        $item = $this->itemInPhase($phase);
        $tank = LootPriority::factory()->role()->create();
        $item->priorities()->attach($tank->id, ['weight' => 0]);

        $table = BuildHighestPriorityStatsTable::run(collect());

        $this->assertCount(0, $table->roleRows);
        $this->assertCount(0, $table->classGroups);
    }

    protected function itemInPhase(Phase $phase): Item
    {
        $raid = Raid::factory()->create(['phase_id' => $phase->id]);
        $item = Item::factory()->create();
        $item->raids()->attach($raid->id);

        return $item;
    }

    protected function table(): HighestPriorityStatsTableData
    {
        return BuildHighestPriorityStatsTable::run(ListPhasesWithTierOnePriorities::run(null)->pluck('id'));
    }
}
