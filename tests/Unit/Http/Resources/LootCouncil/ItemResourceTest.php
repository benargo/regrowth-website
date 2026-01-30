<?php

namespace Tests\Unit\Http\Resources\LootCouncil;

use App\Http\Resources\LootCouncil\ItemResource;
use App\Models\LootCouncil\Item;
use App\Models\LootCouncil\Priority;
use App\Models\TBC\Boss;
use App\Models\TBC\Raid;
use App\Services\Blizzard\ItemService;
use App\Services\Blizzard\MediaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ItemResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockBlizzardServices();
    }

    protected function mockBlizzardServices(array $itemData = [], ?string $iconUrl = null): void
    {
        $defaultItemData = [
            'name' => 'Test Item',
            'item_class' => ['name' => 'Armor'],
            'item_subclass' => ['name' => 'Plate'],
            'quality' => ['type' => 'EPIC', 'name' => 'Epic'],
            'inventory_type' => ['name' => 'Head'],
        ];

        $itemService = Mockery::mock(ItemService::class);
        $itemService->shouldReceive('find')
            ->andReturn(array_merge($defaultItemData, $itemData));

        $mediaService = Mockery::mock(MediaService::class);
        $mediaService->shouldReceive('find')
            ->andReturn([
                'assets' => [
                    ['key' => 'icon', 'value' => 'https://example.com/icon.jpg', 'file_data_id' => 12345],
                ],
            ]);
        $mediaService->shouldReceive('getAssetUrls')
            ->andReturn([12345 => $iconUrl ?? 'https://example.com/stored-icon.jpg']);

        $this->app->instance(ItemService::class, $itemService);
        $this->app->instance(MediaService::class, $mediaService);
    }

    #[Test]
    public function it_returns_id(): void
    {
        $item = Item::factory()->create();

        $resource = new ItemResource($item);
        $array = $resource->toArray(new Request);

        $this->assertSame($item->id, $array['id']);
    }

    #[Test]
    public function it_returns_raid_id_by_default(): void
    {
        $raid = Raid::factory()->create();
        $item = Item::factory()->create(['raid_id' => $raid->id]);

        $resource = new ItemResource($item);
        $array = $resource->toArray(new Request);

        $this->assertSame($raid->id, $array['raid']);
    }

    #[Test]
    public function it_returns_full_raid_when_with_raid_is_called(): void
    {
        $raid = Raid::factory()->create();
        $item = Item::factory()->create(['raid_id' => $raid->id]);
        $item->load('raid');

        $resource = (new ItemResource($item));
        $array = $resource->toArray(new Request);

        $this->assertIsObject($array['raid']);
        $this->assertSame($raid->id, $array['raid']->id);
    }

    #[Test]
    public function it_returns_boss_id_by_default(): void
    {
        $boss = Boss::factory()->create();
        $item = Item::factory()->create(['boss_id' => $boss->id, 'raid_id' => $boss->raid_id]);

        $resource = new ItemResource($item);
        $array = $resource->toArray(new Request);

        $this->assertSame($boss->id, $array['boss']);
    }

    #[Test]
    public function it_returns_null_boss_id_when_item_is_trash_drop(): void
    {
        $item = Item::factory()->trashDrop()->create();

        $resource = new ItemResource($item);
        $array = $resource->toArray(new Request);

        $this->assertNull($array['boss']);
    }

    #[Test]
    public function it_returns_full_boss_when_with_boss_is_called(): void
    {
        $boss = Boss::factory()->create();
        $item = Item::factory()->create(['boss_id' => $boss->id, 'raid_id' => $boss->raid_id]);
        $item->load('boss');

        $resource = new ItemResource($item);
        $array = $resource->toArray(new Request);

        $this->assertIsObject($array['boss']);
        $this->assertSame($boss->id, $array['boss']->id);
    }

    #[Test]
    public function it_returns_group(): void
    {
        $item = Item::factory()->inGroup('Weapons')->create();

        $resource = new ItemResource($item);
        $array = $resource->toArray(new Request);

        $this->assertSame('Weapons', $array['group']);
    }

    #[Test]
    public function it_returns_null_group_when_not_set(): void
    {
        $item = Item::factory()->create(['group' => null]);

        $resource = new ItemResource($item);
        $array = $resource->toArray(new Request);

        $this->assertNull($array['group']);
    }

    #[Test]
    public function it_returns_name_from_blizzard_api(): void
    {
        $this->mockBlizzardServices(['name' => 'Thunderfury, Blessed Blade of the Windseeker']);
        $item = Item::factory()->create();

        $resource = new ItemResource($item);
        $array = $resource->toArray(new Request);

        $this->assertSame('Thunderfury, Blessed Blade of the Windseeker', $array['name']);
    }

    #[Test]
    public function it_returns_fallback_name_when_blizzard_api_fails(): void
    {
        $itemService = Mockery::mock(ItemService::class);
        $itemService->shouldReceive('find')->andThrow(new \Exception('API Error'));

        $mediaService = Mockery::mock(MediaService::class);
        $mediaService->shouldReceive('find')->andThrow(new \Exception('API Error'));

        $this->app->instance(ItemService::class, $itemService);
        $this->app->instance(MediaService::class, $mediaService);

        $item = Item::factory()->create();

        $resource = new ItemResource($item);
        $array = $resource->toArray(new Request);

        $this->assertSame("Item #{$item->id}", $array['name']);
    }

    #[Test]
    public function it_returns_icon_url(): void
    {
        $this->mockBlizzardServices([], 'https://example.com/my-icon.jpg');
        $item = Item::factory()->create();

        $resource = new ItemResource($item);
        $array = $resource->toArray(new Request);

        $this->assertSame('https://example.com/my-icon.jpg', $array['icon']);
    }

    #[Test]
    public function it_returns_null_icon_when_media_api_fails(): void
    {
        $itemService = Mockery::mock(ItemService::class);
        $itemService->shouldReceive('find')->andReturn(['name' => 'Test Item']);

        $mediaService = Mockery::mock(MediaService::class);
        $mediaService->shouldReceive('find')->andThrow(new \Exception('API Error'));

        $this->app->instance(ItemService::class, $itemService);
        $this->app->instance(MediaService::class, $mediaService);

        $item = Item::factory()->create();

        $resource = new ItemResource($item);
        $array = $resource->toArray(new Request);

        $this->assertNull($array['icon']);
    }

    #[Test]
    public function it_returns_item_class(): void
    {
        $this->mockBlizzardServices(['item_class' => ['name' => 'Weapon']]);
        $item = Item::factory()->create();

        $resource = new ItemResource($item);
        $array = $resource->toArray(new Request);

        $this->assertSame('Weapon', $array['item_class']);
    }

    #[Test]
    public function it_returns_item_subclass(): void
    {
        $this->mockBlizzardServices(['item_subclass' => ['name' => 'Sword']]);
        $item = Item::factory()->create();

        $resource = new ItemResource($item);
        $array = $resource->toArray(new Request);

        $this->assertSame('Sword', $array['item_subclass']);
    }

    #[Test]
    public function it_returns_quality(): void
    {
        $this->mockBlizzardServices(['quality' => ['type' => 'LEGENDARY', 'name' => 'Legendary']]);
        $item = Item::factory()->create();

        $resource = new ItemResource($item);
        $array = $resource->toArray(new Request);

        $this->assertSame(['type' => 'LEGENDARY', 'name' => 'Legendary'], $array['quality']);
    }

    #[Test]
    public function it_returns_inventory_type(): void
    {
        $this->mockBlizzardServices(['inventory_type' => ['name' => 'Two-Hand']]);
        $item = Item::factory()->create();

        $resource = new ItemResource($item);
        $array = $resource->toArray(new Request);

        $this->assertSame('Two-Hand', $array['inventory_type']);
    }

    #[Test]
    public function it_returns_notes(): void
    {
        $item = Item::factory()->withNotes('Best in slot for warriors')->create();

        $resource = new ItemResource($item);
        $array = $resource->toArray(new Request);

        $this->assertSame('Best in slot for warriors', $array['notes']);
    }

    #[Test]
    public function it_returns_null_notes_when_not_set(): void
    {
        $item = Item::factory()->create(['notes' => null]);

        $resource = new ItemResource($item);
        $array = $resource->toArray(new Request);

        $this->assertNull($array['notes']);
    }

    #[Test]
    public function it_returns_wowhead_url_with_item_name(): void
    {
        $this->mockBlizzardServices(['name' => 'Thunderfury, Blessed Blade of the Windseeker']);
        $item = Item::factory()->create(['id' => 19019]);

        $resource = new ItemResource($item);
        $array = $resource->toArray(new Request);

        $this->assertSame(
            'https://www.wowhead.com/tbc/item=19019/thunderfury-blessed-blade-of-the-windseeker',
            $array['wowhead_url']
        );
    }

    #[Test]
    public function it_returns_wowhead_url_without_name_when_api_fails(): void
    {
        $itemService = Mockery::mock(ItemService::class);
        $itemService->shouldReceive('find')->andThrow(new \Exception('API Error'));

        $mediaService = Mockery::mock(MediaService::class);
        $mediaService->shouldReceive('find')->andThrow(new \Exception('API Error'));

        $this->app->instance(ItemService::class, $itemService);
        $this->app->instance(MediaService::class, $mediaService);

        $item = Item::factory()->create(['id' => 19019]);

        $resource = new ItemResource($item);
        $array = $resource->toArray(new Request);

        $this->assertSame('https://www.wowhead.com/tbc/item=19019', $array['wowhead_url']);
    }

    #[Test]
    public function it_includes_priorities_when_loaded(): void
    {
        $item = Item::factory()->create();
        $priority = Priority::factory()->create();
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
        $priority = Priority::factory()->create();
        $item->priorities()->attach($priority->id, ['weight' => 1]);

        $resource = new ItemResource($item);
        $array = $resource->resolve(new Request);

        $this->assertArrayNotHasKey('priorities', $array);
    }

    #[Test]
    public function it_returns_all_expected_keys(): void
    {
        $item = Item::factory()->create();

        $resource = new ItemResource($item);
        $array = $resource->toArray(new Request);

        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('raid', $array);
        $this->assertArrayHasKey('boss', $array);
        $this->assertArrayHasKey('group', $array);
        $this->assertArrayHasKey('name', $array);
        $this->assertArrayHasKey('icon', $array);
        $this->assertArrayHasKey('item_class', $array);
        $this->assertArrayHasKey('item_subclass', $array);
        $this->assertArrayHasKey('quality', $array);
        $this->assertArrayHasKey('inventory_type', $array);
        $this->assertArrayHasKey('priorities', $array);
        $this->assertArrayHasKey('notes', $array);
        $this->assertArrayHasKey('wowhead_url', $array);
    }
}
