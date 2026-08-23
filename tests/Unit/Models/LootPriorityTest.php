<?php

namespace Tests\Unit\Models;

use App\Contracts\HasBlizzardIcons;
use App\Models\Item;
use App\Models\LootPriority;
use App\Models\PlayableClass;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Spatie\MediaLibrary\HasMedia;
use Tests\Support\ModelTestCase;

#[Group('loot')]
class LootPriorityTest extends ModelTestCase
{
    protected function modelClass(): string
    {
        return LootPriority::class;
    }

    #[Test]
    public function it_uses_loot_priorities_table(): void
    {
        $model = new LootPriority;

        $this->assertSame('loot_priorities', $model->getTable());
    }

    #[Test]
    public function it_uses_auto_incrementing_id(): void
    {
        $model = new LootPriority;

        $this->assertSame('id', $model->getKeyName());
        $this->assertTrue($model->getIncrementing());
    }

    #[Test]
    public function it_has_expected_fillable_attributes(): void
    {
        $model = new LootPriority;

        $this->assertFillable($model, [
            'title',
            'type',
            'playable_class_id',
        ]);
    }

    #[Test]
    public function it_can_be_created_with_required_attributes(): void
    {
        $priority = $this->create([
            'title' => 'Tank',
            'type' => 'role',
        ]);

        $this->assertTableHas(['title' => 'Tank', 'type' => 'role']);
        $this->assertModelExists($priority);
    }

    // ==================== media ====================

    #[Test]
    public function it_implements_has_media(): void
    {
        $this->assertInstanceOf(HasMedia::class, new LootPriority);
    }

    #[Test]
    public function it_implements_has_blizzard_icons(): void
    {
        $this->assertInstanceOf(HasBlizzardIcons::class, new LootPriority);
    }

    #[Test]
    public function it_registers_blizzard_icons_collection(): void
    {
        $priority = LootPriority::factory()->create();

        $collections = $priority->getRegisteredMediaCollections();

        $this->assertTrue($collections->contains(fn ($c) => $c->name === 'blizzard_icons'));
    }

    // ==================== factory states ====================

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

    // ==================== items relationship ====================

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

    #[Test]
    public function deleting_the_priority_removes_its_item_pivot_rows_but_keeps_the_item(): void
    {
        $priority = $this->create();
        $item = Item::factory()->create();
        $item->priorities()->attach($priority->id, ['weight' => 100]);

        $priority->delete();

        $this->assertDatabaseMissing('pivot_items_priorities', [
            'item_id' => $item->id,
            'priority_id' => $priority->id,
        ]);
        $this->assertModelExists($item);
    }

    // ==================== playable_class relationship ====================

    #[Test]
    public function playable_class_returns_belongs_to_relationship(): void
    {
        $priority = new LootPriority;

        $this->assertInstanceOf(BelongsTo::class, $priority->playableClass());
    }

    #[Test]
    public function it_can_be_created_with_playable_class(): void
    {
        $playableClass = PlayableClass::factory()->create();
        $priority = $this->factory()->withPlayableClass($playableClass)->create();

        $this->assertSame($playableClass->id, $priority->playable_class_id);
        $this->assertInstanceOf(PlayableClass::class, $priority->playableClass);
    }

    #[Test]
    public function playable_class_id_is_mass_assignable(): void
    {
        $playableClass = PlayableClass::factory()->create();

        $priority = $this->create(['playable_class_id' => $playableClass->id]);

        $this->assertSame($playableClass->id, $priority->playable_class_id);
    }

    #[Test]
    public function playable_class_is_null_by_default(): void
    {
        $priority = $this->create();

        $this->assertNull($priority->playable_class_id);
        $this->assertNull($priority->playableClass);
    }

    #[Test]
    public function deleting_the_playable_class_deletes_the_priority(): void
    {
        $playableClass = PlayableClass::factory()->create();
        $priority = $this->factory()->withPlayableClass($playableClass)->create();

        $playableClass->delete();

        $this->assertModelMissing($priority);
    }
}
