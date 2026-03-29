<?php

namespace Tests\Feature\Database\Seeders;

use App\Models\LootCouncil\Item;
use App\Services\Blizzard\ItemService;
use Database\Seeders\ItemSeeder;
use Database\Seeders\TBC\BossSeeder;
use Database\Seeders\TBC\PhaseSeeder;
use Database\Seeders\TBC\RaidSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
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
     * @return array{id: int, name: string}
     */
    private function makeItemResponse(int $id, ?string $name = null): array
    {
        return [
            'id' => $id,
            'name' => $name ?? "Item {$id}",
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

    /**
     * Override the global TestCase ItemService mock with a correctly-shaped one.
     * The global mock's media() response is missing the top-level 'id' key required
     * by ItemMediaCast::fromArray(), so we must replace it here.
     */
    private function mockItemService(?callable $callback = null): void
    {
        $this->instance(
            ItemService::class,
            Mockery::mock(ItemService::class, function (MockInterface $mock) use ($callback) {
                $mock->shouldReceive('find')
                    ->andReturnUsing(fn (int $id) => $this->makeItemResponse($id));

                $mock->shouldReceive('media')
                    ->andReturnUsing(fn (int $id) => $this->makeMediaResponse($id));

                if ($callback) {
                    $callback($mock);
                }
            })
        );
    }

    // ==================== Seeder Behaviour ====================

    #[Test]
    public function seeder_creates_items_with_name_and_icon_from_api(): void
    {
        $this->mockItemService();

        $this->seed(ItemSeeder::class);

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
        $this->mockItemService();

        $this->seed(ItemSeeder::class);

        $itemCount = (new \ReflectionProperty(ItemSeeder::class, 'items'))
            ->getValue(app(ItemSeeder::class));

        $this->seed(ItemSeeder::class);

        $this->assertDatabaseCount('lootcouncil_items', count($itemCount));
    }

    #[Test]
    public function seeder_updates_name_and_icon_on_existing_items(): void
    {
        $this->mockItemService();

        Item::forceCreate([
            'id' => 28453,
            'raid_id' => 1,
            'boss_id' => 1,
            'group' => null,
            'name' => 'Old Name',
            'icon' => null,
        ]);

        $this->seed(ItemSeeder::class);

        $this->assertDatabaseHas('lootcouncil_items', [
            'id' => 28453,
            'name' => 'Item 28453',
        ]);
    }

    #[Test]
    public function seeder_leaves_name_null_when_api_returns_no_name(): void
    {
        $this->instance(
            ItemService::class,
            Mockery::mock(ItemService::class, function (MockInterface $mock) {
                $mock->shouldReceive('find')
                    ->andReturnUsing(fn (int $id) => ['id' => $id]);

                $mock->shouldReceive('media')
                    ->andReturnUsing(fn (int $id) => $this->makeMediaResponse($id));
            })
        );

        $this->seed(ItemSeeder::class);

        $this->assertDatabaseHas('lootcouncil_items', [
            'id' => 28453,
            'name' => null,
        ]);
    }

    #[Test]
    public function seeder_still_creates_items_when_media_api_returns_empty_array(): void
    {
        $this->instance(
            ItemService::class,
            Mockery::mock(ItemService::class, function (MockInterface $mock) {
                $mock->shouldReceive('find')
                    ->andReturnUsing(fn (int $id) => $this->makeItemResponse($id));

                $mock->shouldReceive('media')
                    ->andReturn([]);
            })
        );

        $this->seed(ItemSeeder::class);

        $item = Item::find(28453);

        $this->assertNotNull($item);
        $this->assertSame('Item 28453', $item->name);
        $this->assertNull($item->icon);
    }

    #[Test]
    public function seeder_sets_correct_raid_and_boss_ids_from_static_data(): void
    {
        $this->mockItemService();

        $this->seed(ItemSeeder::class);

        $this->assertDatabaseHas('lootcouncil_items', [
            'id' => 28453,
            'raid_id' => 1,
            'boss_id' => 1,
        ]);
    }

    #[Test]
    public function seeder_skips_item_and_continues_when_api_throws_http_exception(): void
    {
        $this->instance(
            ItemService::class,
            Mockery::mock(ItemService::class, function (MockInterface $mock) {
                $mock->shouldReceive('find')
                    ->with(28453)
                    ->andThrow(new ConnectionException('Connection refused'));

                $mock->shouldReceive('find')
                    ->andReturnUsing(fn (int $id) => $this->makeItemResponse($id));

                $mock->shouldReceive('media')
                    ->andReturnUsing(fn (int $id) => $this->makeMediaResponse($id));
            })
        );

        $this->seed(ItemSeeder::class);

        // The failed item is still created (updateOrCreate ran before the API call) but has no name/icon
        $this->assertDatabaseHas('lootcouncil_items', ['id' => 28453, 'name' => null]);
        // Other items still get name and icon
        $this->assertDatabaseHas('lootcouncil_items', ['id' => 28454, 'name' => 'Item 28454']);
    }
}
