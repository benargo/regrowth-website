<?php

namespace Tests\Feature\Database\Seeders;

use App\Http\Integrations\Blizzard\Requests\Item\GetItemMediaRequest;
use App\Http\Integrations\Blizzard\Requests\Item\GetItemRequest;
use App\Models\LootCouncil\Item;
use Database\Seeders\BossSeeder;
use Database\Seeders\ItemSeeder;
use Database\Seeders\PhaseSeeder;
use Database\Seeders\RaidSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\PendingRequest;
use Saloon\Laravel\Facades\Saloon;
use Tests\TestCase;

class ItemSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([PhaseSeeder::class, RaidSeeder::class, BossSeeder::class]);
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
        ]);
    }

    /**
     * Seed with only the items needed for testing, to avoid processing all 684 items.
     */
    private function seedWithLimitedItems(): void
    {
        $seeder = app(ItemSeeder::class);

        $reflection = new \ReflectionProperty(ItemSeeder::class, 'items');
        $allItems = $reflection->getValue($seeder);

        // Keep only the first 5 items (includes 28453 and 28454 used in assertions)
        $reflection->setValue($seeder, array_slice($allItems, 0, 5));

        Model::unguarded(fn () => $seeder->run());
    }

    // ==================== Seeder Behaviour ====================

    #[Test]
    public function seeder_creates_items_with_name_and_icon_from_api(): void
    {
        $this->fakeSaloon();

        $this->seedWithLimitedItems();

        $item = Item::find(28453);

        $this->assertNotNull($item);
        $this->assertSame('Item 28453', $item->name);
        $this->assertNotNull($item->icon);
        $this->assertSame(
            'https://render.worldofwarcraft.com/eu/icons/56/item_28453.jpg',
            $item->icon->url()
        );
    }

    #[Test]
    public function seeder_is_idempotent_and_running_twice_does_not_create_duplicates(): void
    {
        $this->fakeSaloon();

        $this->seedWithLimitedItems();
        $countAfterFirst = Item::count();

        $this->seedWithLimitedItems();

        $this->assertDatabaseCount('lootcouncil_items', $countAfterFirst);
    }

    #[Test]
    public function seeder_updates_name_and_icon_on_existing_items(): void
    {
        $this->fakeSaloon();

        Item::forceCreate([
            'id' => 28453,
            'raid_id' => 1,
            'boss_id' => 1,
            'group' => null,
            'name' => 'Old Name',
            'icon' => null,
        ]);

        $this->seedWithLimitedItems();

        $this->assertDatabaseHas('lootcouncil_items', [
            'id' => 28453,
            'name' => 'Item 28453',
        ]);
    }

    #[Test]
    public function seeder_sets_correct_raid_and_boss_ids_from_static_data(): void
    {
        $this->fakeSaloon();

        $this->seedWithLimitedItems();

        $this->assertDatabaseHas('lootcouncil_items', [
            'id' => 28453,
            'raid_id' => 1,
            'boss_id' => 1,
        ]);
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
        ]);

        $this->seedWithLimitedItems();

        // The failed item is still created (updateOrCreate ran before the API call) but has no name/icon
        $this->assertDatabaseHas('lootcouncil_items', ['id' => 28453, 'name' => null]);
        // Other items still get name and icon
        $this->assertDatabaseHas('lootcouncil_items', ['id' => 28454, 'name' => 'Item 28454']);
    }
}
