<?php

namespace Tests\Unit\Http\Resources;

use App\Enums\ItemQuality;
use App\Http\Requests\SearchRequest;
use App\Http\Resources\ItemResource;
use App\Models\Boss;
use App\Models\Item;
use App\Models\LootPriority;
use App\Models\Raid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('loot')]
class ItemResourceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_returns_id(): void
    {
        $item = Item::factory()->create();

        $array = (new ItemResource($item))->toArray(new Request);

        $this->assertSame($item->id, $array['id']);
    }

    #[Test]
    public function it_returns_name_from_model(): void
    {
        $item = Item::factory()->create(['name' => 'Thunderfury, Blessed Blade of the Windseeker']);

        $array = (new ItemResource($item))->toArray(new Request);

        $this->assertSame('Thunderfury, Blessed Blade of the Windseeker', $array['name']);
    }

    // ==================== slug ====================

    #[Test]
    public function it_returns_slug_from_model_name(): void
    {
        $item = Item::factory()->create(['name' => 'Thunderfury, Blessed Blade of the Windseeker']);

        $array = (new ItemResource($item))->toArray(new Request);

        $this->assertSame('thunderfury-blessed-blade-of-the-windseeker', $array['slug']);
    }

    // ==================== wowhead url ====================

    #[Test]
    public function it_returns_wowhead_url_with_item_name(): void
    {
        $item = Item::factory()->create(['id' => 19019, 'name' => 'Thunderfury, Blessed Blade of the Windseeker']);

        $array = (new ItemResource($item))->toArray(new Request);

        $this->assertSame(
            'https://www.wowhead.com/tbc/item=19019/thunderfury-blessed-blade-of-the-windseeker',
            $array['wowhead']['url']
        );
    }

    #[Test]
    public function it_returns_wowhead_url_without_slug_when_name_is_null(): void
    {
        $item = Item::factory()->create(['id' => 19019, 'name' => null]);

        $array = (new ItemResource($item))->toArray(new Request);

        $this->assertSame('https://www.wowhead.com/tbc/item=19019', $array['wowhead']['url']);
    }

    // ==================== boss relation ====================

    #[Test]
    public function it_excludes_boss_when_not_loaded(): void
    {
        $boss = Boss::factory()->create();
        $item = Item::factory()->fromBoss($boss)->create();

        $array = (new ItemResource($item))->resolve(new Request);

        $this->assertArrayNotHasKey('boss', $array);
    }

    #[Test]
    public function it_returns_null_boss_when_item_is_trash_drop_and_boss_is_loaded(): void
    {
        $item = Item::factory()->trashDrop()->create();
        $item->load('boss');

        $array = (new ItemResource($item))->toArray(new Request);

        $this->assertNull($array['boss']);
    }

    #[Test]
    public function it_returns_full_boss_when_loaded(): void
    {
        $boss = Boss::factory()->create();
        $item = Item::factory()->fromBoss($boss)->create();
        $item->load('boss');

        $array = (new ItemResource($item))->toArray(new Request);

        $this->assertIsObject($array['boss']);
        $this->assertSame($boss->id, $array['boss']->id);
    }

    // ==================== raids relation ====================

    #[Test]
    public function it_excludes_raids_when_not_loaded(): void
    {
        $raid = Raid::factory()->create();
        $item = Item::factory()->withRaid($raid)->create();

        $array = (new ItemResource($item))->resolve(new Request);

        $this->assertArrayNotHasKey('raids', $array);
    }

    #[Test]
    public function it_returns_the_raids_when_loaded(): void
    {
        $raid = Raid::factory()->create();
        $item = Item::factory()->withRaid($raid)->create();
        $item->load('raids');

        $array = (new ItemResource($item))->toArray(new Request);

        $this->assertCount(1, $array['raids']);
        $this->assertSame($raid->id, $array['raids']->first()->id);
    }

    #[Test]
    public function it_returns_every_raid_for_a_cross_raid_item(): void
    {
        $raids = Raid::factory()->count(2)->create();
        $item = Item::factory()->inRaids($raids->all())->create();
        $item->load('raids');

        $array = (new ItemResource($item))->toArray(new Request);

        $this->assertCount(2, $array['raids']);
    }

    #[Test]
    public function it_returns_an_empty_collection_when_the_item_has_no_raids(): void
    {
        $item = Item::factory()->create();
        $item->load('raids');

        $array = (new ItemResource($item))->toArray(new Request);

        $this->assertCount(0, $array['raids']);
    }

    // ==================== group ====================

    #[Test]
    public function it_returns_group_when_set(): void
    {
        $item = Item::factory()->inGroup('Weapons')->create();

        $array = (new ItemResource($item))->toArray(new Request);

        $this->assertSame('Weapons', $array['group']);
    }

    #[Test]
    public function it_excludes_group_when_not_set(): void
    {
        $item = Item::factory()->create(['group' => null]);

        $array = (new ItemResource($item))->resolve(new Request);

        $this->assertArrayNotHasKey('group', $array);
    }

    // ==================== notes ====================

    #[Test]
    public function it_returns_notes_when_set(): void
    {
        $item = Item::factory()->withNotes('Best in slot for warriors')->create();

        $array = (new ItemResource($item))->toArray(new Request);

        $this->assertSame('Best in slot for warriors', $array['notes']);
    }

    #[Test]
    public function it_excludes_notes_when_not_set(): void
    {
        $item = Item::factory()->create(['notes' => null]);

        $array = (new ItemResource($item))->resolve(new Request);

        $this->assertArrayNotHasKey('notes', $array);
    }

    // ==================== has_notes via search request ====================

    #[Test]
    public function it_returns_has_notes_true_instead_of_notes_when_resolved_via_search_request(): void
    {
        $item = Item::factory()->withNotes('Best in slot for warriors')->create();

        $array = (new ItemResource($item))->resolve(new SearchRequest);

        $this->assertTrue($array['has_notes']);
        $this->assertArrayNotHasKey('notes', $array);
    }

    #[Test]
    public function it_returns_has_notes_false_when_notes_not_set_and_resolved_via_search_request(): void
    {
        $item = Item::factory()->create(['notes' => null]);

        $array = (new ItemResource($item))->resolve(new SearchRequest);

        $this->assertFalse($array['has_notes']);
        $this->assertArrayNotHasKey('notes', $array);
    }

    // ==================== item class, subclass, and inventory type ====================

    #[Test]
    public function it_returns_item_class_when_set_on_model(): void
    {
        $item = Item::factory()->create();
        $item->forceFill(['itemClass' => ['name' => 'Weapon']]);

        $array = (new ItemResource($item))->toArray(new Request);

        $this->assertSame('Weapon', $array['item_class']);
    }

    #[Test]
    public function it_excludes_item_class_when_not_set_on_model(): void
    {
        $item = Item::factory()->create();

        $array = (new ItemResource($item))->resolve(new Request);

        $this->assertArrayNotHasKey('item_class', $array);
    }

    #[Test]
    public function it_returns_item_subclass_when_set_on_model(): void
    {
        $item = Item::factory()->create();
        $item->forceFill(['itemSubclass' => ['name' => 'Sword']]);

        $array = (new ItemResource($item))->toArray(new Request);

        $this->assertSame('Sword', $array['item_subclass']);
    }

    #[Test]
    public function it_excludes_item_subclass_when_not_set_on_model(): void
    {
        $item = Item::factory()->create();

        $array = (new ItemResource($item))->resolve(new Request);

        $this->assertArrayNotHasKey('item_subclass', $array);
    }

    #[Test]
    public function it_returns_inventory_type_when_set_on_model(): void
    {
        $item = Item::factory()->create();
        $item->forceFill(['inventoryType' => ['name' => 'Two-Hand']]);

        $array = (new ItemResource($item))->toArray(new Request);

        $this->assertSame('Two-Hand', $array['inventory_type']);
    }

    #[Test]
    public function it_excludes_inventory_type_when_not_set_on_model(): void
    {
        $item = Item::factory()->create();

        $array = (new ItemResource($item))->resolve(new Request);

        $this->assertArrayNotHasKey('inventory_type', $array);
    }

    // ==================== quality ====================

    #[Test]
    public function it_returns_quality_from_model(): void
    {
        $item = Item::factory()->withQuality(ItemQuality::EPIC)->create();

        $array = (new ItemResource($item))->toArray(new Request);

        $this->assertSame(ItemQuality::EPIC, $array['quality']);
    }

    #[Test]
    public function it_returns_quality_border_class_from_model(): void
    {
        $item = Item::factory()->withQuality(ItemQuality::EPIC)->create();

        $array = (new ItemResource($item))->toArray(new Request);

        $this->assertSame('border-quality-epic', $array['quality_border_class']);
    }

    // ==================== icon url ====================

    #[Test]
    public function it_returns_icon_url(): void
    {
        $item = Item::factory()->create();
        $item->addMediaFromString('BINARY')
            ->usingFileName('foo.jpg')
            ->withCustomProperties(['size' => 56])
            ->toMediaCollection('blizzard_icons');

        $resource = new ItemResource($item);
        $array = $resource->toArray(new Request);

        $this->assertNotNull($array['icon']);
        $this->assertStringContainsString('/icons/56/foo.jpg', $array['icon']);
        $this->assertTrue(URL::hasValidSignature(request()->create($array['icon'])));
    }

    #[Test]
    public function it_returns_null_icon_when_no_media_attached(): void
    {
        $item = Item::factory()->create();

        $resource = new ItemResource($item);
        $array = $resource->toArray(new Request);

        $this->assertNull($array['icon']);
    }

    // ==================== priorities relation ====================

    #[Test]
    public function it_includes_priorities_when_loaded(): void
    {
        $item = Item::factory()->create();
        $priority = LootPriority::factory()->create();
        $item->priorities()->attach($priority->id, ['weight' => 1]);
        $item->load('priorities');

        $resource = new ItemResource($item);
        $array = $resource->toArray(new Request);

        $this->assertArrayHasKey('priorities', $array);
        $this->assertCount(1, $array['priorities']);
    }

    #[Test]
    public function it_excludes_priorities_when_not_loaded(): void
    {
        $item = Item::factory()->create();
        $priority = LootPriority::factory()->create();
        $item->priorities()->attach($priority->id, ['weight' => 1]);

        $resource = new ItemResource($item);
        $array = $resource->resolve(new Request);

        $this->assertArrayNotHasKey('priorities', $array);
    }

    // ==================== full resource shape ====================

    #[Test]
    public function it_returns_all_expected_keys(): void
    {
        $raid = Raid::factory()->create();
        $boss = Boss::factory()->create(['raid_id' => $raid->id]);
        $item = Item::factory()->inGroup('Weapons')->withRaid($raid)->fromBoss($boss)->create();
        $item->load('boss', 'raids');
        $item->forceFill([
            'itemClass' => ['name' => 'Weapon'],
            'itemSubclass' => ['name' => 'Sword'],
            'inventoryType' => ['name' => 'Two-Hand'],
        ]);

        $array = (new ItemResource($item))->toArray(new Request);

        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('name', $array);
        $this->assertArrayHasKey('slug', $array);
        $this->assertArrayHasKey('group', $array);
        $this->assertArrayHasKey('icon', $array);
        $this->assertArrayHasKey('inventory_type', $array);
        $this->assertArrayHasKey('item_class', $array);
        $this->assertArrayHasKey('item_subclass', $array);
        $this->assertArrayHasKey('quality', $array);
        $this->assertArrayHasKey('quality_border_class', $array);
        $this->assertArrayHasKey('wowhead', $array);
        $this->assertArrayHasKey('url', $array['wowhead']);
        $this->assertArrayHasKey('boss', $array);
        $this->assertArrayHasKey('raids', $array);
        $this->assertArrayNotHasKey('wowhead_url', $array);
    }
}
