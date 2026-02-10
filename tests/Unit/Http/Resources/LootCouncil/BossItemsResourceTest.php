<?php

namespace Tests\Unit\Http\Resources\LootCouncil;

use App\Http\Resources\LootCouncil\BossItemsResource;
use App\Models\LootCouncil\Item;
use App\Models\LootCouncil\ItemComment;
use App\Models\LootCouncil\Priority;
use App\Models\TBC\Boss;
use App\Services\Blizzard\ItemService;
use App\Services\Blizzard\MediaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BossItemsResourceTest extends TestCase
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

    protected function createResourceData(int $bossId, $items): array
    {
        return [
            'bossId' => $bossId,
            'items' => $items,
        ];
    }

    /**
     * Prepare items with required relationships and counts.
     *
     * @param  array<int>  $itemIds
     * @param  array<string>  $relations
     */
    protected function prepareItems(array $itemIds, array $relations = ['priorities']): \Illuminate\Database\Eloquent\Collection
    {
        return Item::query()
            ->whereIn('id', $itemIds)
            ->with($relations)
            ->withCount('comments')
            ->get();
    }

    #[Test]
    public function it_returns_boss_id(): void
    {
        $boss = Boss::factory()->create();
        Item::factory()->count(2)->fromBoss($boss)->create();
        $items = Item::query()->where('boss_id', $boss->id)->with('priorities')->withCount('comments')->get();

        $resource = new BossItemsResource($this->createResourceData($boss->id, $items));
        $array = $resource->toArray(new Request);

        $this->assertSame($boss->id, $array['bossId']);
    }

    #[Test]
    public function it_returns_items_array(): void
    {
        $boss = Boss::factory()->create();
        Item::factory()->count(3)->fromBoss($boss)->create();
        $items = Item::query()->where('boss_id', $boss->id)->with('priorities')->withCount('comments')->get();

        $resource = new BossItemsResource($this->createResourceData($boss->id, $items));
        $array = $resource->toArray(new Request);

        $this->assertIsArray($array['items']);
        $this->assertCount(3, $array['items']);
    }

    #[Test]
    public function it_returns_item_id(): void
    {
        $boss = Boss::factory()->create();
        $item = Item::factory()->fromBoss($boss)->create();
        $items = $this->prepareItems([$item->id]);

        $resource = new BossItemsResource($this->createResourceData($boss->id, $items));
        $array = $resource->toArray(new Request);

        $this->assertSame($item->id, $array['items'][0]['id']);
    }

    #[Test]
    public function it_returns_raid_id_by_default(): void
    {
        $boss = Boss::factory()->create();
        $item = Item::factory()->fromBoss($boss)->create();
        $items = $this->prepareItems([$item->id]);

        $resource = new BossItemsResource($this->createResourceData($boss->id, $items));
        $array = $resource->toArray(new Request);

        $this->assertSame($boss->raid_id, $array['items'][0]['raid']);
    }

    #[Test]
    public function it_returns_full_raid_when_loaded(): void
    {
        $boss = Boss::factory()->create();
        $item = Item::factory()->fromBoss($boss)->create();
        $items = $this->prepareItems([$item->id], ['raid', 'priorities']);

        $resource = new BossItemsResource($this->createResourceData($boss->id, $items));
        $array = $resource->toArray(new Request);

        $this->assertIsObject($array['items'][0]['raid']);
        $this->assertSame($boss->raid_id, $array['items'][0]['raid']->id);
    }

    #[Test]
    public function it_returns_boss_id_in_item_by_default(): void
    {
        $boss = Boss::factory()->create();
        $item = Item::factory()->fromBoss($boss)->create();
        $items = $this->prepareItems([$item->id]);

        $resource = new BossItemsResource($this->createResourceData($boss->id, $items));
        $array = $resource->toArray(new Request);

        $this->assertSame($boss->id, $array['items'][0]['boss']);
    }

    #[Test]
    public function it_returns_full_boss_when_loaded(): void
    {
        $boss = Boss::factory()->create();
        $item = Item::factory()->fromBoss($boss)->create();
        $items = $this->prepareItems([$item->id], ['boss', 'priorities']);

        $resource = new BossItemsResource($this->createResourceData($boss->id, $items));
        $array = $resource->toArray(new Request);

        $this->assertIsObject($array['items'][0]['boss']);
        $this->assertSame($boss->id, $array['items'][0]['boss']->id);
    }

    #[Test]
    public function it_returns_group(): void
    {
        $boss = Boss::factory()->create();
        $item = Item::factory()->fromBoss($boss)->inGroup('Weapons')->create();
        $items = $this->prepareItems([$item->id]);

        $resource = new BossItemsResource($this->createResourceData($boss->id, $items));
        $array = $resource->toArray(new Request);

        $this->assertSame('Weapons', $array['items'][0]['group']);
    }

    #[Test]
    public function it_returns_name_from_blizzard_api(): void
    {
        $this->mockBlizzardServices(['name' => 'Thunderfury, Blessed Blade of the Windseeker']);

        $boss = Boss::factory()->create();
        $item = Item::factory()->fromBoss($boss)->create();
        $items = $this->prepareItems([$item->id]);

        $resource = new BossItemsResource($this->createResourceData($boss->id, $items));
        $array = $resource->toArray(new Request);

        $this->assertSame('Thunderfury, Blessed Blade of the Windseeker', $array['items'][0]['name']);
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

        $boss = Boss::factory()->create();
        $item = Item::factory()->fromBoss($boss)->create();
        $items = $this->prepareItems([$item->id]);

        $resource = new BossItemsResource($this->createResourceData($boss->id, $items));
        $array = $resource->toArray(new Request);

        $this->assertSame("Item #{$item->id}", $array['items'][0]['name']);
    }

    #[Test]
    public function it_returns_icon_url(): void
    {
        $this->mockBlizzardServices([], 'https://example.com/my-icon.jpg');

        $boss = Boss::factory()->create();
        $item = Item::factory()->fromBoss($boss)->create();
        $items = $this->prepareItems([$item->id]);

        $resource = new BossItemsResource($this->createResourceData($boss->id, $items));
        $array = $resource->toArray(new Request);

        $this->assertSame('https://example.com/my-icon.jpg', $array['items'][0]['icon']);
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

        $boss = Boss::factory()->create();
        $item = Item::factory()->fromBoss($boss)->create();
        $items = $this->prepareItems([$item->id]);

        $resource = new BossItemsResource($this->createResourceData($boss->id, $items));
        $array = $resource->toArray(new Request);

        $this->assertNull($array['items'][0]['icon']);
    }

    #[Test]
    public function it_returns_null_icon_when_assets_are_empty(): void
    {
        $itemService = Mockery::mock(ItemService::class);
        $itemService->shouldReceive('find')->andReturn(['name' => 'Test Item']);

        $mediaService = Mockery::mock(MediaService::class);
        $mediaService->shouldReceive('find')->andReturn(['assets' => []]);

        $this->app->instance(ItemService::class, $itemService);
        $this->app->instance(MediaService::class, $mediaService);

        $boss = Boss::factory()->create();
        $item = Item::factory()->fromBoss($boss)->create();
        $items = $this->prepareItems([$item->id]);

        $resource = new BossItemsResource($this->createResourceData($boss->id, $items));
        $array = $resource->toArray(new Request);

        $this->assertNull($array['items'][0]['icon']);
    }

    #[Test]
    public function it_returns_priorities_collection(): void
    {
        $boss = Boss::factory()->create();
        $item = Item::factory()->fromBoss($boss)->create();
        $priority = Priority::factory()->create();
        $item->priorities()->attach($priority->id, ['weight' => 1]);

        $items = $this->prepareItems([$item->id]);

        $resource = new BossItemsResource($this->createResourceData($boss->id, $items));
        $array = $resource->toArray(new Request);

        $this->assertCount(1, $array['items'][0]['priorities']);
    }

    #[Test]
    public function it_returns_has_notes_true_when_notes_exist(): void
    {
        $boss = Boss::factory()->create();
        $item = Item::factory()->fromBoss($boss)->withNotes('Some notes')->create();
        $items = $this->prepareItems([$item->id]);

        $resource = new BossItemsResource($this->createResourceData($boss->id, $items));
        $array = $resource->toArray(new Request);

        $this->assertTrue($array['items'][0]['hasNotes']);
    }

    #[Test]
    public function it_returns_has_notes_false_when_notes_are_null(): void
    {
        $boss = Boss::factory()->create();
        $item = Item::factory()->fromBoss($boss)->create(['notes' => null]);
        $items = $this->prepareItems([$item->id]);

        $resource = new BossItemsResource($this->createResourceData($boss->id, $items));
        $array = $resource->toArray(new Request);

        $this->assertFalse($array['items'][0]['hasNotes']);
    }

    #[Test]
    public function it_returns_comments_count_for_item(): void
    {
        $boss = Boss::factory()->create();
        $item = Item::factory()->fromBoss($boss)->create();
        ItemComment::factory()->count(3)->create(['item_id' => $item->id]);

        $items = $this->prepareItems([$item->id]);

        $resource = new BossItemsResource($this->createResourceData($boss->id, $items));
        $array = $resource->toArray(new Request);

        $this->assertSame(3, $array['items'][0]['commentsCount']);
    }

    #[Test]
    public function it_returns_wowhead_url(): void
    {
        $boss = Boss::factory()->create();
        $item = Item::factory()->fromBoss($boss)->create(['id' => 19019]);
        $items = $this->prepareItems([$item->id]);

        $resource = new BossItemsResource($this->createResourceData($boss->id, $items));
        $array = $resource->toArray(new Request);

        $this->assertSame('https://www.wowhead.com/tbc/item=19019', $array['items'][0]['wowhead_url']);
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

        $boss = Boss::factory()->create();
        $item = Item::factory()->fromBoss($boss)->create(['id' => 19019]);
        $items = $this->prepareItems([$item->id]);

        $resource = new BossItemsResource($this->createResourceData($boss->id, $items));
        $array = $resource->toArray(new Request);

        $this->assertSame('https://www.wowhead.com/tbc/item=19019', $array['items'][0]['wowhead_url']);
    }

    #[Test]
    public function it_returns_total_comments_count_for_all_items(): void
    {
        $boss = Boss::factory()->create();
        $item1 = Item::factory()->fromBoss($boss)->create();
        $item2 = Item::factory()->fromBoss($boss)->create();
        ItemComment::factory()->count(2)->create(['item_id' => $item1->id]);
        ItemComment::factory()->count(3)->create(['item_id' => $item2->id]);

        $items = $this->prepareItems([$item1->id, $item2->id]);

        $resource = new BossItemsResource($this->createResourceData($boss->id, $items));
        $array = $resource->toArray(new Request);

        $this->assertSame(5, $array['commentsCount']);
    }

    #[Test]
    public function it_returns_zero_comments_count_when_no_comments(): void
    {
        $boss = Boss::factory()->create();
        $item = Item::factory()->fromBoss($boss)->create();
        $items = $this->prepareItems([$item->id]);

        $resource = new BossItemsResource($this->createResourceData($boss->id, $items));
        $array = $resource->toArray(new Request);

        $this->assertSame(0, $array['commentsCount']);
    }

    #[Test]
    public function it_returns_all_expected_keys_for_item(): void
    {
        $boss = Boss::factory()->create();
        $item = Item::factory()->fromBoss($boss)->create();
        $items = $this->prepareItems([$item->id]);

        $resource = new BossItemsResource($this->createResourceData($boss->id, $items));
        $array = $resource->toArray(new Request);

        $this->assertArrayHasKey('bossId', $array);
        $this->assertArrayHasKey('items', $array);
        $this->assertArrayHasKey('commentsCount', $array);

        $itemArray = $array['items'][0];
        $this->assertArrayHasKey('id', $itemArray);
        $this->assertArrayHasKey('raid', $itemArray);
        $this->assertArrayHasKey('boss', $itemArray);
        $this->assertArrayHasKey('group', $itemArray);
        $this->assertArrayHasKey('name', $itemArray);
        $this->assertArrayHasKey('icon', $itemArray);
        $this->assertArrayHasKey('priorities', $itemArray);
        $this->assertArrayHasKey('hasNotes', $itemArray);
        $this->assertArrayHasKey('commentsCount', $itemArray);
        $this->assertArrayHasKey('wowhead_url', $itemArray);
    }

    #[Test]
    public function it_handles_empty_items_collection(): void
    {
        $boss = Boss::factory()->create();
        $items = Item::query()->whereIn('id', [])->with('priorities')->withCount('comments')->get();

        $resource = new BossItemsResource($this->createResourceData($boss->id, $items));
        $array = $resource->toArray(new Request);

        $this->assertSame($boss->id, $array['bossId']);
        $this->assertIsArray($array['items']);
        $this->assertCount(0, $array['items']);
        $this->assertSame(0, $array['commentsCount']);
    }
}
