<?php

namespace Tests\Unit\Models\LootCouncil;

use App\Casts\ItemMediaCast;
use App\Models\LootCouncil\Item;
use App\Models\LootCouncil\Priority;
use App\Models\TBC\Boss;
use App\Models\TBC\Raid;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\ModelTestCase;

class ItemTest extends ModelTestCase
{
    protected function modelClass(): string
    {
        return Item::class;
    }

    #[Test]
    public function it_uses_lootcouncil_items_table(): void
    {
        $model = new Item;

        $this->assertSame('lootcouncil_items', $model->getTable());
    }

    #[Test]
    public function it_uses_auto_incrementing_id(): void
    {
        $model = new Item;

        $this->assertSame('id', $model->getKeyName());
        $this->assertTrue($model->getIncrementing());
    }

    #[Test]
    public function it_has_expected_fillable_attributes(): void
    {
        $model = new Item;

        $this->assertFillable($model, [
            'raid_id',
            'boss_id',
            'name',
            'icon',
            'group',
            'notes',
        ]);
    }

    #[Test]
    public function it_can_be_created_with_required_attributes(): void
    {
        $raid = Raid::factory()->create();

        $item = $this->create([
            'raid_id' => $raid->id,
        ]);

        $this->assertTableHas(['raid_id' => $raid->id]);
        $this->assertModelExists($item);
    }

    #[Test]
    public function it_can_be_created_with_all_attributes(): void
    {
        $raid = Raid::factory()->create();
        $boss = Boss::factory()->create(['raid_id' => $raid->id]);

        $item = $this->create([
            'raid_id' => $raid->id,
            'boss_id' => $boss->id,
            'name' => 'Warglaive of Azzinoth',
            'group' => 'Tokens',
            'notes' => 'Priority for tanks',
        ]);

        $this->assertTableHas([
            'raid_id' => $raid->id,
            'boss_id' => $boss->id,
            'name' => 'Warglaive of Azzinoth',
            'group' => 'Tokens',
            'notes' => 'Priority for tanks',
        ]);
        $this->assertModelExists($item);
    }

    #[Test]
    public function it_allows_null_boss_id(): void
    {
        $raid = Raid::factory()->create();

        $item = $this->create([
            'raid_id' => $raid->id,
            'boss_id' => null,
        ]);

        $this->assertNull($item->boss_id);
        $this->assertModelExists($item);
    }

    #[Test]
    public function it_allows_null_group(): void
    {
        $raid = Raid::factory()->create();

        $item = $this->create([
            'raid_id' => $raid->id,
            'group' => null,
        ]);

        $this->assertNull($item->group);
        $this->assertModelExists($item);
    }

    #[Test]
    public function it_allows_null_notes(): void
    {
        $raid = Raid::factory()->create();

        $item = $this->create([
            'raid_id' => $raid->id,
            'notes' => null,
        ]);

        $this->assertNull($item->notes);
        $this->assertModelExists($item);
    }

    #[Test]
    public function factory_creates_valid_model(): void
    {
        $item = $this->create();

        $this->assertNotNull($item->raid_id);
        $this->assertModelExists($item);
    }

    #[Test]
    public function factory_from_boss_state_sets_boss(): void
    {
        $item = $this->factory()->fromBoss()->create();

        $this->assertNotNull($item->boss_id);
        $this->assertNotNull($item->boss);
    }

    #[Test]
    public function factory_trash_drop_state_sets_null_boss(): void
    {
        $item = $this->factory()->trashDrop()->create();

        $this->assertNull($item->boss_id);
    }

    #[Test]
    public function factory_in_group_state_sets_group(): void
    {
        $item = $this->factory()->inGroup('Weapons')->create();

        $this->assertSame('Weapons', $item->group);
    }

    #[Test]
    public function factory_with_notes_state_sets_notes(): void
    {
        $item = $this->factory()->withNotes('Tank priority')->create();

        $this->assertSame('Tank priority', $item->notes);
    }

    #[Test]
    public function it_belongs_to_a_raid(): void
    {
        $raid = Raid::factory()->create();
        $item = $this->create(['raid_id' => $raid->id]);

        $this->assertRelation($item, 'raid', BelongsTo::class);
        $this->assertTrue($item->raid->is($raid));
    }

    #[Test]
    public function it_belongs_to_a_boss(): void
    {
        $raid = Raid::factory()->create();
        $boss = Boss::factory()->create(['raid_id' => $raid->id]);
        $item = $this->create(['raid_id' => $raid->id, 'boss_id' => $boss->id]);

        $this->assertRelation($item, 'boss', BelongsTo::class);
        $this->assertTrue($item->boss->is($boss));
    }

    #[Test]
    public function it_belongs_to_many_priorities(): void
    {
        $item = $this->create();
        $priorities = Priority::factory()->count(3)->create();

        foreach ($priorities as $priority) {
            $item->priorities()->attach($priority->id, ['weight' => 100]);
        }

        $item->refresh();

        $this->assertRelation($item, 'priorities', BelongsToMany::class);
        $this->assertCount(3, $item->priorities);
    }

    #[Test]
    public function it_has_weight_on_priority_pivot(): void
    {
        $item = $this->create();
        $priority = Priority::factory()->create();

        $item->priorities()->attach($priority->id, ['weight' => 50]);

        $item->refresh();

        $this->assertSame(50, $item->priorities->first()->pivot->weight);
    }

    #[Test]
    public function it_allows_null_name(): void
    {
        $item = $this->create(['name' => null]);

        $this->assertNull($item->name);
    }

    #[Test]
    public function it_allows_null_icon(): void
    {
        $item = $this->create(['icon' => null]);

        $this->assertNull($item->icon);
    }

    #[Test]
    public function it_casts_icon_to_item_media_cast(): void
    {
        $item = $this->factory()->withIcon()->create();

        $this->assertInstanceOf(ItemMediaCast::class, $item->icon);
    }

    #[Test]
    public function slug_is_derived_from_name(): void
    {
        $item = $this->create(['name' => 'Warglaive of Azzinoth']);

        $this->assertSame('warglaive-of-azzinoth', $item->slug);
    }

    #[Test]
    public function slug_is_empty_string_when_name_is_null(): void
    {
        $item = $this->create(['name' => null]);

        $this->assertSame('', $item->slug);
    }

    #[Test]
    public function wowhead_url_includes_slug_when_name_is_set(): void
    {
        $item = $this->create(['name' => 'Warglaive of Azzinoth']);

        $this->assertSame(
            "https://www.wowhead.com/tbc/item={$item->id}/warglaive-of-azzinoth",
            $item->wowhead_url,
        );
    }

    #[Test]
    public function wowhead_url_excludes_slug_when_name_is_null(): void
    {
        $item = $this->create(['name' => null]);

        $this->assertSame(
            "https://www.wowhead.com/tbc/item={$item->id}",
            $item->wowhead_url,
        );
    }

    #[Test]
    public function factory_with_name_state_sets_name(): void
    {
        $item = $this->factory()->withName('Blessed Blade of the Windseeker')->create();

        $this->assertSame('Blessed Blade of the Windseeker', $item->name);
    }

    #[Test]
    public function factory_with_icon_state_sets_icon(): void
    {
        $item = $this->factory()->withIcon()->create();

        $this->assertInstanceOf(ItemMediaCast::class, $item->icon);
        $this->assertNotEmpty($item->icon->assets);
    }
}
