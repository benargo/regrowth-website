<?php

namespace Tests\Unit\Models;

use App\Contracts\HasBlizzardIcons;
use App\Enums\ItemQuality;
use App\Http\Integrations\Blizzard\Data\Item\ItemData;
use App\Models\Boss;
use App\Models\Item;
use App\Models\LootPriority;
use App\Models\Raid;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Spatie\MediaLibrary\HasMedia;
use Tests\Support\InteractsWithFullTextSearch;
use Tests\Support\ModelTestCase;

#[Group('loot')]
class ItemTest extends ModelTestCase
{
    use InteractsWithFullTextSearch;

    protected function modelClass(): string
    {
        return Item::class;
    }

    #[Test]
    public function it_uses_items_table(): void
    {
        $model = new Item;

        $this->assertSame('items', $model->getTable());
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
            'quality',
            'group',
            'notes',
        ]);
    }

    #[Test]
    public function it_has_expected_hidden_attributes(): void
    {
        $model = new Item;

        $this->assertHidden($model, [
            'wowhead_url',
            'created_at',
            'updated_at',
        ]);
    }

    #[Test]
    public function it_can_be_created_without_raid_id(): void
    {
        $item = $this->create(['raid_id' => null]);

        $this->assertNull($item->raid_id);
        $this->assertModelExists($item);
    }

    #[Test]
    public function it_can_be_created_with_raid_id(): void
    {
        $raid = Raid::factory()->create();

        $item = $this->create(['raid_id' => $raid->id]);

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
        $item = $this->create(['boss_id' => null]);

        $this->assertNull($item->boss_id);
        $this->assertModelExists($item);
    }

    #[Test]
    public function it_allows_null_group(): void
    {
        $item = $this->create(['group' => null]);

        $this->assertNull($item->group);
        $this->assertModelExists($item);
    }

    #[Test]
    public function it_allows_null_notes(): void
    {
        $item = $this->create(['notes' => null]);

        $this->assertNull($item->notes);
        $this->assertModelExists($item);
    }

    #[Test]
    public function factory_default_has_null_raid_id(): void
    {
        $item = $this->create();

        $this->assertNull($item->raid_id);
    }

    #[Test]
    public function factory_with_raid_state_sets_raid_id(): void
    {
        $raid = Raid::factory()->create();

        $item = $this->factory()->withRaid($raid)->create();

        $this->assertSame($raid->id, $item->raid_id);
    }

    #[Test]
    public function factory_with_raid_state_creates_raid_when_none_given(): void
    {
        $item = $this->factory()->withRaid()->create();

        $this->assertNotNull($item->raid_id);
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
        $priorities = LootPriority::factory()->count(3)->create();

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
        $priority = LootPriority::factory()->create();

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
    public function quality_is_cast_to_item_quality_enum(): void
    {
        $item = $this->factory()->withQuality(ItemQuality::EPIC)->create();

        $item->fresh();

        $this->assertInstanceOf(ItemQuality::class, $item->quality);
        $this->assertSame(ItemQuality::EPIC, $item->quality);
    }

    #[Test]
    public function factory_with_quality_state_sets_quality(): void
    {
        $item = $this->factory()->withQuality(ItemQuality::RARE)->create();

        $this->assertSame(ItemQuality::RARE, $item->quality);
    }

    #[Test]
    public function fill_blizzard_data_sets_name_and_blizzard_attributes(): void
    {
        $item = $this->create(['name' => null]);
        $data = ItemData::from([
            'id' => 19019,
            'name' => 'Thunderfury, Blessed Blade of the Windseeker',
            'quality' => ['type' => 'LEGENDARY', 'name' => 'Legendary'],
            'level' => 80,
            'required_level' => 60,
            'media' => ['key' => ['href' => 'https://eu.api.blizzard.com/data/wow/media/item/19019'], 'id' => 19019],
            'item_class' => ['key' => ['href' => 'https://eu.api.blizzard.com/data/wow/item-class/2'], 'name' => 'Weapon', 'id' => 2],
            'item_subclass' => ['key' => ['href' => 'https://eu.api.blizzard.com/data/wow/item-class/2/item-subclass/7'], 'name' => 'One-Handed Sword', 'id' => 7],
            'inventory_type' => ['type' => 'WEAPON', 'name' => 'One-Hand'],
            'purchase_price' => 0,
            'sell_price' => 12345,
        ]);

        $item->fillBlizzardData($data);

        $this->assertSame('Thunderfury, Blessed Blade of the Windseeker', $item->name);
        $this->assertSame('Weapon', $item->itemClass['name']);
        $this->assertSame('One-Handed Sword', $item->itemSubclass['name']);
        $this->assertSame('One-Hand', $item->inventoryType['name']);
    }

    #[Test]
    public function fill_blizzard_data_returns_the_same_model_instance(): void
    {
        $item = $this->create();
        $data = ItemData::from([
            'id' => 19019,
            'name' => 'Thunderfury, Blessed Blade of the Windseeker',
            'quality' => ['type' => 'LEGENDARY', 'name' => 'Legendary'],
            'level' => 80,
            'required_level' => 60,
            'media' => ['key' => ['href' => 'https://eu.api.blizzard.com/data/wow/media/item/19019'], 'id' => 19019],
            'item_class' => ['key' => ['href' => 'https://eu.api.blizzard.com/data/wow/item-class/2'], 'name' => 'Weapon', 'id' => 2],
            'item_subclass' => ['key' => ['href' => 'https://eu.api.blizzard.com/data/wow/item-class/2/item-subclass/7'], 'name' => 'One-Handed Sword', 'id' => 7],
            'inventory_type' => ['type' => 'WEAPON', 'name' => 'One-Hand'],
            'purchase_price' => 0,
            'sell_price' => 12345,
        ]);

        $result = $item->fillBlizzardData($data);

        $this->assertSame($item, $result);
    }

    #[Test]
    public function fill_blizzard_data_does_not_persist_to_database(): void
    {
        $item = $this->create(['name' => null]);
        $data = ItemData::from([
            'id' => 19019,
            'name' => 'Thunderfury, Blessed Blade of the Windseeker',
            'quality' => ['type' => 'LEGENDARY', 'name' => 'Legendary'],
            'level' => 80,
            'required_level' => 60,
            'media' => ['key' => ['href' => 'https://eu.api.blizzard.com/data/wow/media/item/19019'], 'id' => 19019],
            'item_class' => ['key' => ['href' => 'https://eu.api.blizzard.com/data/wow/item-class/2'], 'name' => 'Weapon', 'id' => 2],
            'item_subclass' => ['key' => ['href' => 'https://eu.api.blizzard.com/data/wow/item-class/2/item-subclass/7'], 'name' => 'One-Handed Sword', 'id' => 7],
            'inventory_type' => ['type' => 'WEAPON', 'name' => 'One-Hand'],
            'purchase_price' => 0,
            'sell_price' => 12345,
        ]);

        $item->fillBlizzardData($data);

        $this->assertNull($item->fresh()->name);
    }

    #[Test]
    public function it_implements_media_library_contracts(): void
    {
        $model = new Item;

        $this->assertInstanceOf(HasMedia::class, $model);
        $this->assertInstanceOf(HasBlizzardIcons::class, $model);
    }

    #[Test]
    public function it_stores_a_single_blizzard_icon(): void
    {
        Storage::fake('public');

        $item = $this->create();

        $item->addMediaFromString('BINARY')
            ->usingFileName('inv_sword_04.jpg')
            ->withCustomProperties(['size' => 56])
            ->toMediaCollection('blizzard_icons');

        $item->addMediaFromString('BINARY2')
            ->usingFileName('inv_sword_05.jpg')
            ->withCustomProperties(['size' => 56])
            ->toMediaCollection('blizzard_icons');

        // singleFile() collection keeps only the most recent media item.
        $this->assertCount(1, $item->getMedia('blizzard_icons'));
        $this->assertSame('inv_sword_05.jpg', $item->getFirstMedia('blizzard_icons')->file_name);
    }

    // ==========================================
    // Search
    // ==========================================

    #[Test]
    public function matching_name_matches_case_insensitively(): void
    {
        $this->usingModel(Item::class)->withCommittedTransaction(
            create: fn () => ['match' => Item::factory()->withName('Archbishop\'s Slippers')->create()],
            assert: function (array $items) {
                $results = Item::query()->matchingName('ARCHBISHOP')->get();

                $this->assertCount(1, $results);
                $this->assertTrue($results->contains('id', $items['match']->id));
            },
        );
    }

    #[Test]
    public function matching_name_matches_a_partial_name(): void
    {
        $this->usingModel(Item::class)->withCommittedTransaction(
            create: fn () => [
                'match' => Item::factory()->withName('Archbishop\'s Slippers')->create(),
                'decoy' => Item::factory()->withName('Thunderfury')->create(),
            ],
            assert: function (array $items) {
                $results = Item::query()->matchingName('slipper')->get();

                $this->assertCount(1, $results);
                $this->assertTrue($results->contains('id', $items['match']->id));
            },
        );
    }

    #[Test]
    public function matching_name_excludes_items_with_a_null_name(): void
    {
        Item::factory()->create();

        $this->assertCount(0, Item::query()->matchingName('anything')->get());
    }
}
