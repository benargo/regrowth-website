<?php

namespace Tests\Unit\Models\LootCouncil;

use App\Models\LootCouncil\Item;
use App\Models\LootCouncil\Priority;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\ModelTestCase;

class PriorityTest extends ModelTestCase
{
    protected function modelClass(): string
    {
        return Priority::class;
    }

    #[Test]
    public function it_uses_lootcouncil_priorities_table(): void
    {
        $model = new Priority;

        $this->assertSame('lootcouncil_priorities', $model->getTable());
    }

    #[Test]
    public function it_uses_auto_incrementing_id(): void
    {
        $model = new Priority;

        $this->assertSame('id', $model->getKeyName());
        $this->assertTrue($model->getIncrementing());
    }

    #[Test]
    public function it_has_expected_fillable_attributes(): void
    {
        $model = new Priority;

        $this->assertFillable($model, [
            'title',
            'type',
            'media',
        ]);
    }

    #[Test]
    public function it_can_be_created_with_required_attributes(): void
    {
        $priority = $this->create([
            'title' => 'Tank',
            'type' => 'role',
            'media' => ['media_type' => 'spell', 'media_id' => 71],
        ]);

        $this->assertTableHas(['title' => 'Tank', 'type' => 'role']);
        $this->assertModelExists($priority);
    }

    #[Test]
    public function factory_creates_valid_model(): void
    {
        $priority = $this->create();

        $this->assertNotEmpty($priority->title);
        $this->assertNotEmpty($priority->type);
        $this->assertModelExists($priority);
    }

    #[Test]
    public function factory_role_state_sets_type_to_role(): void
    {
        $priority = $this->factory()->role()->create();

        $this->assertSame('role', $priority->type);
    }

    #[Test]
    public function factory_class_type_state_sets_type_to_class(): void
    {
        $priority = $this->factory()->classType()->create();

        $this->assertSame('class', $priority->type);
    }

    #[Test]
    public function factory_spec_state_sets_type_to_spec(): void
    {
        $priority = $this->factory()->spec()->create();

        $this->assertSame('spec', $priority->type);
    }

    #[Test]
    public function it_belongs_to_many_items(): void
    {
        $priority = $this->create();
        $items = Item::factory()->count(2)->create();

        foreach ($items as $item) {
            $item->priorities()->attach($priority->id, ['weight' => 100]);
        }

        $priority->refresh();

        $this->assertRelation($priority, 'items', BelongsToMany::class);
        $this->assertCount(2, $priority->items);
    }

    #[Test]
    public function it_has_weight_on_pivot(): void
    {
        $priority = $this->create();
        $item = Item::factory()->create();

        $item->priorities()->attach($priority->id, ['weight' => 75]);

        $priority->refresh();

        $this->assertSame(75, $priority->items->first()->pivot->weight);
    }
}
