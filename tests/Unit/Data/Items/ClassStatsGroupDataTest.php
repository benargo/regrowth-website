<?php

namespace Tests\Unit\Data\Items;

use App\Data\Items\ClassStatsGroupData;
use App\Data\Items\PriorityStatsRowData;
use App\Enums\LootPriorityType;
use App\Models\PlayableClass;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('loot')]
class ClassStatsGroupDataTest extends TestCase
{
    // ==================== fromPlayableClass ====================

    #[Test]
    public function from_playable_class_carries_the_class_fields_counts_and_children(): void
    {
        $children = collect([$this->child()]);

        $data = ClassStatsGroupData::fromPlayableClass($this->playableClass('https://example.com/druid.jpg'), [1 => 2, 2 => 5], $children);

        $this->assertSame('class', $data->kind);
        $this->assertSame(11, $data->id);
        $this->assertSame('Druid', $data->title);
        $this->assertSame('druid', $data->slug);
        $this->assertSame('https://example.com/druid.jpg', $data->icon);
        $this->assertSame([1 => 2, 2 => 5], $data->counts);
        $this->assertSame($children, $data->children);
    }

    #[Test]
    public function from_playable_class_sums_the_counts_into_the_total(): void
    {
        $data = ClassStatsGroupData::fromPlayableClass($this->playableClass(), [1 => 2, 2 => 5, 3 => 0], collect());

        $this->assertSame(7, $data->total);
    }

    #[Test]
    public function from_playable_class_sets_a_null_icon_when_the_class_has_no_media(): void
    {
        $data = ClassStatsGroupData::fromPlayableClass($this->playableClass(''), [1 => 0], collect());

        $this->assertNull($data->icon);
    }

    // ==================== toArray ====================

    #[Test]
    public function to_array_emits_the_stats_table_group_shape_with_nested_children(): void
    {
        $data = ClassStatsGroupData::fromPlayableClass($this->playableClass('https://example.com/druid.jpg'), [1 => 2], collect([$this->child()]));

        $this->assertEquals([
            'kind' => 'class',
            'id' => 11,
            'title' => 'Druid',
            'slug' => 'druid',
            'icon' => 'https://example.com/druid.jpg',
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
        ], $data->toArray());
    }

    private function playableClass(string $iconUrl = ''): PlayableClass
    {
        $playableClass = Mockery::mock(PlayableClass::class)->makePartial();
        $playableClass->forceFill(['id' => 11, 'name' => 'Druid', 'slug' => 'druid']);
        $playableClass->shouldReceive('getFirstMediaUrl')->with('blizzard_icons')->andReturn($iconUrl);

        return $playableClass;
    }

    private function child(): PriorityStatsRowData
    {
        return new PriorityStatsRowData(
            id: 7,
            title: 'Feral',
            type: LootPriorityType::SPEC,
            icon: null,
            counts: [1 => 2],
            total: 2,
        );
    }
}
