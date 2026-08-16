<?php

namespace Tests\Feature\Database\Seeders;

use App\Enums\ItemQuality;
use App\Http\Integrations\Blizzard\Requests\Item\GetItemMediaRequest;
use App\Http\Integrations\Blizzard\Requests\Item\GetItemRequest;
use App\Http\Integrations\Blizzard\Requests\Render\FetchIconRequest;
use App\Jobs\AttachBlizzardIconToModel;
use App\Models\Item;
use Database\Seeders\BossSeeder;
use Database\Seeders\ItemSeeder;
use Database\Seeders\PhaseSeeder;
use Database\Seeders\RaidSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\PendingRequest;
use Saloon\Laravel\Facades\Saloon;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\TestCase;

#[Group('loot')]
class ItemSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([PhaseSeeder::class, RaidSeeder::class, BossSeeder::class]);

        Storage::fake('public');
    }

    /**
     * Returns a correctly-shaped Blizzard item response.
     *
     * @return array<string, mixed>
     */
    private function makeItemResponse(int $id, ?string $name = null): array
    {
        return [
            'id' => $id,
            'name' => $name ?? "Item {$id}",
            'quality' => ['type' => 'UNCOMMON', 'name' => 'Uncommon'],
            'level' => 115,
            'required_level' => 70,
            'media' => ['key' => ['href' => "https://example.test/media/{$id}"]],
            'item_class' => ['key' => ['href' => 'https://example.test/item-class/2'], 'name' => 'Weapon', 'id' => 2],
            'item_subclass' => ['key' => ['href' => 'https://example.test/item-subclass/2-7'], 'name' => 'Sword', 'id' => 7],
            'inventory_type' => ['type' => 'WEAPONMAINHAND', 'name' => 'Main Hand'],
            'purchase_price' => 0,
            'sell_price' => 0,
        ];
    }

    /**
     * Returns a correctly-shaped Blizzard media response.
     *
     * @return array{id: int, assets: array<int, array{key: string, value: string, file_data_id: int}>}
     */
    private function makeMediaResponse(int $id): array
    {
        return [
            'id' => $id,
            'assets' => [
                [
                    'key' => 'icon',
                    'value' => "https://render.worldofwarcraft.com/eu/icons/56/item_{$id}.jpg",
                    'file_data_id' => $id * 10,
                ],
            ],
        ];
    }

    private function extractItemIdFromRequest(PendingRequest $request): int
    {
        return (int) last(explode('/', parse_url($request->getUrl(), PHP_URL_PATH)));
    }

    private function fakeSaloon(): void
    {
        Saloon::fake([
            'eu.battle.net/oauth/token' => MockResponse::make(
                body: ['access_token' => 'test_token', 'token_type' => 'bearer', 'expires_in' => 3600],
                status: 200,
            ),
            GetItemRequest::class => function (PendingRequest $request): MockResponse {
                return MockResponse::make(body: $this->makeItemResponse($this->extractItemIdFromRequest($request)), status: 200);
            },
            GetItemMediaRequest::class => function (PendingRequest $request): MockResponse {
                return MockResponse::make(body: $this->makeMediaResponse($this->extractItemIdFromRequest($request)), status: 200);
            },
            FetchIconRequest::class => MockResponse::make(body: 'BINARY', status: 200),
        ]);
    }

    /**
     * Seed with only the items needed for testing, to avoid processing all 684 items.
     */
    private function seedWithLimitedItems(): ItemSeeder
    {
        $seeder = app(ItemSeeder::class);

        $reflection = new \ReflectionProperty(ItemSeeder::class, 'items');
        $allItems = $reflection->getValue($seeder);

        // Keep only the first 5 items (includes 28453 and 28454 used in assertions)
        $reflection->setValue($seeder, array_slice($allItems, 0, 5));

        Model::unguarded(fn () => $seeder->run());

        return $seeder;
    }

    // ==================== seeder behaviour ====================

    #[Test]
    public function seeder_creates_items_with_name_and_icon_from_api(): void
    {
        $this->fakeSaloon();

        $this->seedWithLimitedItems();

        $item = Item::find(28453);

        $this->assertNotNull($item);
        $this->assertSame('Item 28453', $item->name);
        $this->assertSame(ItemQuality::UNCOMMON, $item->quality);
        $this->assertTrue($item->hasMedia('blizzard_icons'));

        $media = $item->getFirstMedia('blizzard_icons');
        $this->assertSame('item_28453.jpg', $media->file_name);
        $this->assertSame(56, $media->getCustomProperty('size'));
        Storage::disk('public')->assertExists('blizzard-cdn/icons/56/item_28453.jpg');
        $this->assertDatabaseHas('media', [
            'model_type' => Item::class,
            'collection_name' => 'blizzard_icons',
            'file_name' => 'item_28453.jpg',
        ]);
    }

    #[Test]
    public function seeder_is_idempotent_and_running_twice_does_not_create_duplicates(): void
    {
        $this->fakeSaloon();

        $this->seedWithLimitedItems();
        $countAfterFirst = Item::count();

        $this->seedWithLimitedItems();

        $this->assertDatabaseCount('items', $countAfterFirst);
    }

    #[Test]
    public function seeder_updates_name_and_icon_on_existing_items(): void
    {
        $this->fakeSaloon();

        Item::forceCreate([
            'id' => 28453,
            'boss_id' => 1,
            'group' => null,
            'name' => 'Old Name',
            'quality' => ItemQuality::COMMON->value,
        ]);

        $this->seedWithLimitedItems();

        $this->assertDatabaseHas('items', [
            'id' => 28453,
            'name' => 'Item 28453',
        ]);
        $this->assertTrue(Item::find(28453)->hasMedia('blizzard_icons'));
    }

    #[Test]
    public function seeder_attaches_the_raids_and_boss_from_static_data(): void
    {
        $this->fakeSaloon();

        $this->seedWithLimitedItems();

        $this->assertDatabaseHas('items', [
            'id' => 28453,
            'boss_id' => 1,
        ]);
        $this->assertDatabaseHas('pivot_items_raids', [
            'item_id' => 28453,
            'raid_id' => 1,
        ]);
    }

    #[Test]
    public function seeder_attaches_both_raids_to_cross_raid_trash_items(): void
    {
        $this->fakeSaloon();

        $this->seedSpecificItems([32589, 32590, 32591, 32592, 32609, 34009]);

        foreach ([32589, 32590, 32591, 32592, 32609, 34009] as $itemId) {
            $this->assertEqualsCanonicalizing(
                [6, 7],
                Item::find($itemId)->raids->pluck('id')->all(),
                "Item {$itemId} is not attached to both Hyjal Summit and Black Temple",
            );
        }
    }

    #[Test]
    public function seeder_is_idempotent_for_cross_raid_items(): void
    {
        $this->fakeSaloon();

        $this->seedSpecificItems([32589]);
        $this->seedSpecificItems([32589]);

        $this->assertSame(2, Item::find(32589)->raids()->count());
    }

    #[Test]
    public function every_seeder_row_declares_at_least_one_raid(): void
    {
        $seeder = app(ItemSeeder::class);
        $rows = (new \ReflectionProperty(ItemSeeder::class, 'items'))->getValue($seeder);

        foreach ($rows as $row) {
            $this->assertArrayHasKey('raid_ids', $row, "Row {$row['id']} is missing raid_ids");
            $this->assertIsArray($row['raid_ids'], "Row {$row['id']} raid_ids is not an array");
            $this->assertNotEmpty($row['raid_ids'], "Row {$row['id']} has an empty raid_ids");
            $this->assertArrayNotHasKey('raid_id', $row, "Row {$row['id']} still has the old raid_id key");
        }
    }

    #[Test]
    public function seeder_skips_item_and_continues_when_api_returns_404(): void
    {
        Saloon::fake([
            'eu.battle.net/oauth/token' => MockResponse::make(
                body: ['access_token' => 'test_token', 'token_type' => 'bearer', 'expires_in' => 3600],
                status: 200,
            ),
            GetItemRequest::class => function (PendingRequest $request): MockResponse {
                $id = $this->extractItemIdFromRequest($request);

                if ($id === 28453) {
                    return MockResponse::make(
                        body: ['code' => 404, 'type' => 'BLZWEBAPI00000404', 'detail' => 'Not Found'],
                        status: 404,
                    );
                }

                return MockResponse::make(body: $this->makeItemResponse($id), status: 200);
            },
            GetItemMediaRequest::class => function (PendingRequest $request): MockResponse {
                return MockResponse::make(body: $this->makeMediaResponse($this->extractItemIdFromRequest($request)), status: 200);
            },
            FetchIconRequest::class => MockResponse::make(body: 'BINARY', status: 200),
        ]);

        $seeder = $this->seedWithLimitedItems();

        // The failed item is not created — both API requests must succeed before the model is persisted
        $this->assertDatabaseMissing('items', ['id' => 28453]);
        // Other items still get name and icon
        $this->assertDatabaseHas('items', ['id' => 28454, 'name' => 'Item 28454']);
        $this->assertSame([28453], $seeder->skippedItemIds());
    }

    #[Test]
    public function seeder_skips_item_when_icon_fetch_returns_404(): void
    {
        Saloon::fake([
            'eu.battle.net/oauth/token' => MockResponse::make(
                body: ['access_token' => 'test_token', 'token_type' => 'bearer', 'expires_in' => 3600],
                status: 200,
            ),
            GetItemRequest::class => function (PendingRequest $request): MockResponse {
                return MockResponse::make(body: $this->makeItemResponse($this->extractItemIdFromRequest($request)), status: 200);
            },
            GetItemMediaRequest::class => function (PendingRequest $request): MockResponse {
                return MockResponse::make(body: $this->makeMediaResponse($this->extractItemIdFromRequest($request)), status: 200);
            },
            FetchIconRequest::class => function (PendingRequest $request): MockResponse {
                if (str_contains($request->getUrl(), 'item_28453.jpg')) {
                    return MockResponse::make(body: ['code' => 404], status: 404);
                }

                return MockResponse::make(body: 'BINARY', status: 200);
            },
        ]);

        $this->seedWithLimitedItems();

        // Item 28453 should have its name set (name update happens before icon fetch)
        $item28453 = Item::find(28453);
        $this->assertNotNull($item28453);
        $this->assertSame('Item 28453', $item28453->name);
        // But no icon — the MediaNotFoundException was caught and the seeder continued
        $this->assertFalse($item28453->hasMedia('blizzard_icons'));

        // The seeder continued processing subsequent items
        $item28454 = Item::find(28454);
        $this->assertNotNull($item28454);
        $this->assertSame('Item 28454', $item28454->name);
        $this->assertTrue($item28454->hasMedia('blizzard_icons'));
    }

    #[Test]
    public function seeder_does_not_reattach_icon_when_already_present(): void
    {
        $this->fakeSaloon();

        $this->seedWithLimitedItems();
        $mediaCountAfterFirstRun = Media::count();

        $this->seedWithLimitedItems();

        $this->assertSame($mediaCountAfterFirstRun, Media::count());
    }

    #[Test]
    public function seeder_dispatches_retry_job_when_icon_fetch_returns_403(): void
    {
        Queue::fake();

        Saloon::fake([
            'eu.battle.net/oauth/token' => MockResponse::make(
                body: ['access_token' => 'test_token', 'token_type' => 'bearer', 'expires_in' => 3600],
                status: 200,
            ),
            GetItemRequest::class => function (PendingRequest $request): MockResponse {
                return MockResponse::make(body: $this->makeItemResponse($this->extractItemIdFromRequest($request)), status: 200);
            },
            GetItemMediaRequest::class => function (PendingRequest $request): MockResponse {
                return MockResponse::make(body: $this->makeMediaResponse($this->extractItemIdFromRequest($request)), status: 200);
            },
            FetchIconRequest::class => function (PendingRequest $request): MockResponse {
                if (str_contains($request->getUrl(), 'item_28453.jpg')) {
                    return MockResponse::make(body: ['code' => 403, 'detail' => 'Forbidden'], status: 403);
                }

                return MockResponse::make(body: 'BINARY', status: 200);
            },
        ]);

        $this->seedWithLimitedItems();

        // Item 28453 should be persisted with its name set before the icon fetch fails
        $item28453 = Item::find(28453);
        $this->assertNotNull($item28453);
        $this->assertSame('Item 28453', $item28453->name);
        // No icon yet — job is deferred
        $this->assertFalse($item28453->hasMedia('blizzard_icons'));

        // The retry job should have been dispatched
        Queue::assertPushed(AttachBlizzardIconToModel::class, function (AttachBlizzardIconToModel $job) {
            return $job->modelClass === Item::class && $job->modelKey === 28453;
        });

        // Other items should still get their icons immediately
        $item28454 = Item::find(28454);
        $this->assertNotNull($item28454);
        $this->assertSame('Item 28454', $item28454->name);
        $this->assertTrue($item28454->hasMedia('blizzard_icons'));
    }

    /**
     * Seed only the given item ids, so a test does not process all 684 rows.
     *
     * @param  array<int, int>  $itemIds
     */
    private function seedSpecificItems(array $itemIds): void
    {
        $seeder = app(ItemSeeder::class);

        $reflection = new \ReflectionProperty(ItemSeeder::class, 'items');
        $allItems = $reflection->getValue($seeder);

        $reflection->setValue($seeder, array_values(array_filter(
            $allItems,
            fn (array $item): bool => in_array($item['id'], $itemIds, true),
        )));

        Model::unguarded(fn () => $seeder->run());
    }
}
