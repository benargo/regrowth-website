<?php

namespace Tests\Unit\Models;

use App\Contracts\Commentable;
use App\Contracts\HasBlizzardIcons;
use App\Enums\ItemQuality;
use App\Http\Integrations\Blizzard\Data\Item\ItemData;
use App\Models\Boss;
use App\Models\Item;
use App\Models\LootPriority;
use App\Models\Phase;
use App\Models\Raid;
use Illuminate\Broadcasting\Channel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Spatie\MediaLibrary\HasMedia;
use Tests\Support\ModelTestCase;

#[Group('loot')]
class ItemTest extends ModelTestCase
{
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

    // ==================== persistence and factory ====================

    #[Test]
    public function it_can_be_created_with_all_attributes(): void
    {
        $raid = Raid::factory()->create();
        $boss = Boss::factory()->create(['raid_id' => $raid->id]);

        $item = $this->create([
            'boss_id' => $boss->id,
            'name' => 'Warglaive of Azzinoth',
            'group' => 'Tokens',
            'notes' => 'Priority for tanks',
        ]);

        $this->assertTableHas([
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

    // ==================== relationships ====================

    #[Test]
    public function it_belongs_to_a_boss(): void
    {
        $raid = Raid::factory()->create();
        $boss = Boss::factory()->create(['raid_id' => $raid->id]);
        $item = $this->factory()->fromBoss($boss)->create();

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
    public function deleting_the_item_removes_its_priority_pivot_rows_but_keeps_the_priority(): void
    {
        $item = $this->create();
        $priority = LootPriority::factory()->create();
        $item->priorities()->attach($priority->id, ['weight' => 100]);

        $item->delete();

        $this->assertDatabaseMissing('pivot_items_priorities', [
            'item_id' => $item->id,
            'priority_id' => $priority->id,
        ]);
        $this->assertModelExists($priority);
    }

    #[Test]
    public function it_belongs_to_many_raids(): void
    {
        $item = $this->create();
        $raids = Raid::factory()->count(2)->create();

        $item->raids()->attach($raids->pluck('id'));
        $item->refresh();

        $this->assertRelation($item, 'raids', BelongsToMany::class);
        $this->assertCount(2, $item->raids);
    }

    #[Test]
    public function it_has_no_raids_by_default(): void
    {
        $item = $this->create();

        $this->assertCount(0, $item->raids);
    }

    #[Test]
    public function it_has_many_phases_through_its_raids(): void
    {
        $phaseA = Phase::factory()->create();
        $phaseB = Phase::factory()->create();
        $raidA = Raid::factory()->create(['phase_id' => $phaseA->id]);
        $raidB = Raid::factory()->create(['phase_id' => $phaseB->id]);
        $item = $this->factory()->inRaids([$raidA, $raidB])->create();

        $this->assertRelation($item, 'phases', HasMany::class);
        $this->assertCount(2, $item->phases);
        $this->assertTrue($item->phases->contains($phaseA));
        $this->assertTrue($item->phases->contains($phaseB));
    }

    #[Test]
    public function it_only_returns_distinct_phases_when_raids_share_a_phase(): void
    {
        $phase = Phase::factory()->create();
        $raids = Raid::factory()->count(2)->create(['phase_id' => $phase->id]);
        $item = $this->factory()->inRaids($raids->all())->create();

        $this->assertCount(1, $item->phases);
    }

    #[Test]
    public function it_has_no_phases_by_default(): void
    {
        $item = $this->create();

        $this->assertCount(0, $item->phases);
    }

    #[Test]
    public function the_same_raid_cannot_be_attached_twice(): void
    {
        $item = $this->create();
        $raid = Raid::factory()->create();

        $item->raids()->attach($raid->id);
        $item->raids()->syncWithoutDetaching([$raid->id]);
        $item->refresh();

        $this->assertCount(1, $item->raids);
    }

    #[Test]
    public function factory_in_raids_state_attaches_the_given_raids(): void
    {
        $raids = Raid::factory()->count(2)->create();

        $item = $this->factory()->inRaids($raids->all())->create();

        $this->assertCount(2, $item->raids);
        $this->assertEqualsCanonicalizing(
            $raids->pluck('id')->all(),
            $item->raids->pluck('id')->all(),
        );
    }

    #[Test]
    public function factory_with_raid_state_attaches_a_single_raid(): void
    {
        $raid = Raid::factory()->create();

        $item = $this->factory()->withRaid($raid)->create();

        $this->assertCount(1, $item->raids);
        $this->assertTrue($item->raids->first()->is($raid));
    }

    #[Test]
    public function factory_from_boss_state_attaches_the_bosses_raid(): void
    {
        $item = $this->factory()->fromBoss()->create();

        $this->assertNotNull($item->boss_id);
        $this->assertCount(1, $item->raids);
        $this->assertSame($item->boss->raid_id, $item->raids->first()->id);
    }

    // ==================== name, slug, wowhead url, and quality ====================

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

    // ==================== fill blizzard data and trash scope ====================

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
    public function trash_scope_only_includes_items_without_a_boss(): void
    {
        $raid = Raid::factory()->create();
        $boss = Boss::factory()->create(['raid_id' => $raid->id]);
        $bossItem = $this->factory()->fromBoss($boss)->create();
        $trashItem = $this->factory()->trashDrop()->create();

        $trashItems = Item::trash()->get();

        $this->assertTrue($trashItems->contains($trashItem));
        $this->assertFalse($trashItems->contains($bossItem));
    }

    // ==================== media and commenting ====================

    #[Test]
    public function it_implements_media_library_contracts(): void
    {
        $model = new Item;

        $this->assertInstanceOf(HasMedia::class, $model);
        $this->assertInstanceOf(HasBlizzardIcons::class, $model);
        $this->assertInstanceOf(Commentable::class, $model);
    }

    #[Test]
    public function comment_channel_is_scoped_to_the_item_id(): void
    {
        $item = $this->create();

        $channel = $item->commentChannel();

        $this->assertInstanceOf(Channel::class, $channel);
        $this->assertSame("item.{$item->id}", $channel->name);
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
}
