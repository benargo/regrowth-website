<?php

namespace Tests\Unit\Data\Items;

use App\Data\Items\PriorityStatsRowData;
use App\Enums\LootPriorityType;
use App\Models\LootPriority;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('loot')]
class PriorityStatsRowDataTest extends TestCase
{
    // ==================== fromPriority ====================

    #[Test]
    public function from_priority_carries_the_priority_fields_and_counts(): void
    {
        $data = PriorityStatsRowData::fromPriority($this->priority('https://example.com/tank.jpg'), [1 => 3, 2 => 4]);

        $this->assertSame('priority', $data->kind);
        $this->assertSame(7, $data->id);
        $this->assertSame('Tank', $data->title);
        $this->assertSame(LootPriorityType::ROLE, $data->type);
        $this->assertSame('https://example.com/tank.jpg', $data->icon);
        $this->assertSame([1 => 3, 2 => 4], $data->counts);
    }

    #[Test]
    public function from_priority_sums_the_counts_into_the_total(): void
    {
        $data = PriorityStatsRowData::fromPriority($this->priority(), [1 => 3, 2 => 4, 3 => 0]);

        $this->assertSame(7, $data->total);
    }

    #[Test]
    public function from_priority_sets_a_null_icon_when_the_priority_has_no_media(): void
    {
        $data = PriorityStatsRowData::fromPriority($this->priority(''), [1 => 0]);

        $this->assertNull($data->icon);
    }

    // ==================== toArray ====================

    #[Test]
    public function to_array_emits_the_stats_table_row_shape(): void
    {
        $data = PriorityStatsRowData::fromPriority($this->priority('https://example.com/tank.jpg'), [1 => 3, 2 => 4]);

        $this->assertEquals([
            'kind' => 'priority',
            'id' => 7,
            'title' => 'Tank',
            'type' => LootPriorityType::ROLE->value,
            'icon' => 'https://example.com/tank.jpg',
            'counts' => [1 => 3, 2 => 4],
            'total' => 7,
        ], $data->toArray());
    }

    private function priority(string $iconUrl = ''): LootPriority
    {
        $priority = Mockery::mock(LootPriority::class)->makePartial();
        $priority->forceFill(['id' => 7, 'title' => 'Tank', 'type' => LootPriorityType::ROLE]);
        $priority->shouldReceive('getFirstMediaUrl')->with('blizzard_icons')->andReturn($iconUrl);

        return $priority;
    }
}
