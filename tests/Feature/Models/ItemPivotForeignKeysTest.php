<?php

namespace Tests\Feature\Models;

use App\Models\Item;
use App\Models\LootPriority;
use App\Models\Raid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ItemPivotForeignKeysTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function an_item_can_be_attached_to_a_raid_via_the_uuid_pivot(): void
    {
        $item = Item::factory()->create();
        $raid = Raid::factory()->create();

        $item->raids()->attach($raid);

        $this->assertDatabaseHas('pivot_items_raids', ['item_id' => $item->id, 'raid_id' => $raid->id]);
    }

    #[Test]
    public function deleting_an_item_cascades_to_its_raid_pivot_rows(): void
    {
        $item = Item::factory()->create();
        $raid = Raid::factory()->create();
        $item->raids()->attach($raid);

        $item->delete();

        $this->assertDatabaseMissing('pivot_items_raids', ['raid_id' => $raid->id]);
    }

    #[Test]
    public function an_item_can_be_attached_to_a_priority_via_the_uuid_pivot(): void
    {
        $item = Item::factory()->create();
        $priority = LootPriority::factory()->create();

        $item->priorities()->attach($priority, ['weight' => 1]);

        $this->assertDatabaseHas('pivot_items_priorities', ['item_id' => $item->id, 'priority_id' => $priority->id]);
    }
}
