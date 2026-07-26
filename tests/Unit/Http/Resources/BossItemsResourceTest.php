<?php

namespace Tests\Unit\Http\Resources;

use App\Http\Integrations\Blizzard\Requests\Item\GetItemRequest;
use App\Http\Resources\BossItemsResource;
use App\Models\Boss;
use App\Models\Comment;
use App\Models\Item;
use App\Models\LootPriority;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Http\Faking\MockResponse;
use Saloon\Laravel\Facades\Saloon;
use Tests\Support\Blizzard\MocksBlizzardServices;
use Tests\TestCase;

#[Group('loot')]
#[Group('raiding')]
class BossItemsResourceTest extends TestCase
{
    use MocksBlizzardServices;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockItemService();
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
    protected function prepareItems(array $itemIds, array $relations = ['priorities']): Collection
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
        $this->mockItemService(['name' => 'Thunderfury, Blessed Blade of the Windseeker']);

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
        Saloon::fake([
            'eu.battle.net/oauth/token' => MockResponse::make(['access_token' => 'test_token', 'token_type' => 'bearer', 'expires_in' => 3600]),
            GetItemRequest::class => MockResponse::make(body: ['type' => 'BLZWEBAPI00000404', 'detail' => 'Not Found'], status: 404),
        ]);

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
        $boss = Boss::factory()->create();
        $item = Item::factory()->fromBoss($boss)->create();
        $item->addMediaFromString('BINARY')
            ->usingFileName('foo.jpg')
            ->withCustomProperties(['size' => 56])
            ->toMediaCollection('blizzard_icons');
        $items = $this->prepareItems([$item->id]);

        $resource = new BossItemsResource($this->createResourceData($boss->id, $items));
        $array = $resource->toArray(new Request);

        $this->assertNotNull($array['items'][0]['icon']);
        $this->assertStringContainsString('/icons/56/foo.jpg', $array['items'][0]['icon']);
        $this->assertTrue(URL::hasValidSignature(request()->create($array['items'][0]['icon'])));
    }

    #[Test]
    public function it_returns_null_icon_when_no_media_attached(): void
    {
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
        $priority = LootPriority::factory()->create();
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
        Comment::factory()->count(3)->for($item, 'commentable')->create();

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
        Saloon::fake([
            'eu.battle.net/oauth/token' => MockResponse::make(['access_token' => 'test_token', 'token_type' => 'bearer', 'expires_in' => 3600]),
            GetItemRequest::class => MockResponse::make(body: ['type' => 'BLZWEBAPI00000404', 'detail' => 'Not Found'], status: 404),
        ]);

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
        Comment::factory()->count(2)->for($item1, 'commentable')->create();
        Comment::factory()->count(3)->for($item2, 'commentable')->create();

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
