<?php

namespace Tests\Unit\Models\LootCouncil;

use App\Models\LootCouncil\Item;
use App\Models\LootCouncil\ItemPriority;
use App\Models\LootCouncil\Priority;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\ModelTestCase;

class ItemPriorityTest extends ModelTestCase
{
    protected function modelClass(): string
    {
        return ItemPriority::class;
    }

    #[Test]
    public function it_uses_lootcouncil_item_priorities_table(): void
    {
        $model = new ItemPriority;

        $this->assertSame('lootcouncil_item_priorities', $model->getTable());
    }

    #[Test]
    public function it_uses_auto_incrementing_id(): void
    {
        $model = new ItemPriority;

        $this->assertSame('id', $model->getKeyName());
        $this->assertTrue($model->getIncrementing());
    }

    #[Test]
    public function it_has_expected_fillable_attributes(): void
    {
        $model = new ItemPriority;

        $this->assertFillable($model, [
            'item_id',
            'priority_id',
            'weight',
        ]);
    }

    #[Test]
    public function it_can_be_created_with_required_attributes(): void
    {
        $item = Item::factory()->create();
        $priority = Priority::factory()->create();

        $itemPriority = $this->create([
            'item_id' => $item->id,
            'priority_id' => $priority->id,
            'weight' => 50,
        ]);

        $this->assertTableHas([
            'item_id' => $item->id,
            'priority_id' => $priority->id,
            'weight' => 50,
        ]);
        $this->assertModelExists($itemPriority);
    }

    #[Test]
    public function factory_creates_valid_model(): void
    {
        $itemPriority = $this->create();

        $this->assertNotNull($itemPriority->item_id);
        $this->assertNotNull($itemPriority->priority_id);
        $this->assertNotNull($itemPriority->weight);
        $this->assertModelExists($itemPriority);
    }

    #[Test]
    public function factory_weight_state_sets_specific_weight(): void
    {
        $itemPriority = $this->factory()->weight(75)->create();

        $this->assertSame(75, $itemPriority->weight);
    }

    #[Test]
    public function factory_high_priority_state_sets_high_weight(): void
    {
        $itemPriority = $this->factory()->highPriority()->create();

        $this->assertGreaterThanOrEqual(80, $itemPriority->weight);
        $this->assertLessThanOrEqual(100, $itemPriority->weight);
    }

    #[Test]
    public function factory_low_priority_state_sets_low_weight(): void
    {
        $itemPriority = $this->factory()->lowPriority()->create();

        $this->assertGreaterThanOrEqual(1, $itemPriority->weight);
        $this->assertLessThanOrEqual(20, $itemPriority->weight);
    }

    #[Test]
    public function it_belongs_to_an_item(): void
    {
        $item = Item::factory()->create();
        $itemPriority = $this->create(['item_id' => $item->id]);

        $this->assertRelation($itemPriority, 'item', BelongsTo::class);
        $this->assertTrue($itemPriority->item->is($item));
    }

    #[Test]
    public function it_belongs_to_a_priority(): void
    {
        $priority = Priority::factory()->create();
        $itemPriority = $this->create(['priority_id' => $priority->id]);

        $this->assertRelation($itemPriority, 'priority', BelongsTo::class);
        $this->assertTrue($itemPriority->priority->is($priority));
    }
}
