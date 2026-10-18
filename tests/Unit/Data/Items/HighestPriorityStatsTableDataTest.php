<?php

namespace Tests\Unit\Data\Items;

use App\Data\Items\ClassStatsGroupData;
use App\Data\Items\HighestPriorityStatsTableData;
use App\Data\Items\PriorityStatsRowData;
use App\Enums\LootPriorityType;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('loot')]
class HighestPriorityStatsTableDataTest extends TestCase
{
    // ==================== toArray ====================

    #[Test]
    public function to_array_emits_role_rows_and_class_groups_in_snake_case(): void
    {
        $this->assertEquals([
            'role_rows' => [
                [
                    'kind' => 'priority',
                    'id' => 1,
                    'title' => 'Tank',
                    'type' => LootPriorityType::ROLE->value,
                    'icon' => null,
                    'counts' => [1 => 3],
                    'total' => 3,
                ],
            ],
            'class_groups' => [
                [
                    'kind' => 'class',
                    'id' => 11,
                    'title' => 'Druid',
                    'slug' => 'druid',
                    'icon' => null,
                    'counts' => [1 => 2],
                    'total' => 2,
                    'children' => [
                        [
                            'kind' => 'priority',
                            'id' => 7,
                            'title' => 'Feral',
                            'type' => LootPriorityType::SPEC->value,
                            'icon' => null,
                            'counts' => [1 => 2],
                            'total' => 2,
                        ],
                    ],
                ],
            ],
        ], $this->table()->toArray());
    }

    #[Test]
    public function to_array_emits_empty_lists_for_an_empty_table(): void
    {
        $table = new HighestPriorityStatsTableData(roleRows: collect(), classGroups: collect());

        $this->assertSame(['role_rows' => [], 'class_groups' => []], $table->toArray());
    }

    // ==================== from ====================

    #[Test]
    public function from_rebuilds_the_table_from_its_array_form(): void
    {
        $table = HighestPriorityStatsTableData::from($this->table()->toArray());

        $this->assertEquals($this->table(), $table);
    }

    #[Test]
    public function from_hydrates_rows_groups_and_children_as_data_objects(): void
    {
        $table = HighestPriorityStatsTableData::from($this->table()->toArray());

        $this->assertContainsOnlyInstancesOf(PriorityStatsRowData::class, $table->roleRows);
        $this->assertContainsOnlyInstancesOf(ClassStatsGroupData::class, $table->classGroups);
        $this->assertContainsOnlyInstancesOf(PriorityStatsRowData::class, $table->classGroups[0]->children);
        $this->assertSame(LootPriorityType::SPEC, $table->classGroups[0]->children[0]->type);
    }

    private function table(): HighestPriorityStatsTableData
    {
        return new HighestPriorityStatsTableData(
            roleRows: collect([$this->row(id: 1, title: 'Tank', type: LootPriorityType::ROLE, counts: [1 => 3])]),
            classGroups: collect([
                new ClassStatsGroupData(
                    id: 11,
                    title: 'Druid',
                    slug: 'druid',
                    icon: null,
                    counts: [1 => 2],
                    total: 2,
                    children: collect([$this->row(id: 7, title: 'Feral', type: LootPriorityType::SPEC, counts: [1 => 2])]),
                ),
            ]),
        );
    }

    /**
     * @param  array<int, int>  $counts
     */
    private function row(int $id, string $title, LootPriorityType $type, array $counts): PriorityStatsRowData
    {
        return new PriorityStatsRowData(
            id: $id,
            title: $title,
            type: $type,
            icon: null,
            counts: $counts,
            total: array_sum($counts),
        );
    }
}
