<?php

namespace Tests\Feature\Database\Seeders;

use App\Models\LootCouncil\Item;
use App\Services\Blizzard\BlizzardService;
use Database\Seeders\ItemSeeder;
use Database\Seeders\TBC\BossSeeder;
use Database\Seeders\TBC\PhaseSeeder;
use Database\Seeders\TBC\RaidSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
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
     * Mock BlizzardService with correctly-shaped findItem/getItemMedia responses.
     */
    private function mockBlizzardService(?callable $callback = null): void
    {
        $this->mock(BlizzardService::class, function (MockInterface $mock) use ($callback) {
            $mock->shouldReceive('findItem')
                ->andReturnUsing(fn (int $id) => $this->makeItemResponse($id));

            $mock->shouldReceive('getItemMedia')
                ->andReturnUsing(fn (int $id) => $this->makeMediaResponse($id));

            if ($callback) {
                $callback($mock);
            }
        });
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
        $this->mockBlizzardService();

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
        $this->mockBlizzardService();

        $this->seedWithLimitedItems();
        $countAfterFirst = Item::count();

        $this->seedWithLimitedItems();

        $this->assertDatabaseCount('lootcouncil_items', $countAfterFirst);
    }

    #[Test]
    public function seeder_updates_name_and_icon_on_existing_items(): void
    {
        $this->mockBlizzardService();

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
    public function seeder_leaves_name_null_when_api_returns_no_name(): void
    {
        $this->mock(BlizzardService::class, function (MockInterface $mock) {
            $mock->shouldReceive('findItem')
                ->andReturnUsing(fn (int $id) => ['id' => $id]);

            $mock->shouldReceive('getItemMedia')
                ->andReturnUsing(fn (int $id) => $this->makeMediaResponse($id));
        });

        $this->seedWithLimitedItems();

        $this->assertDatabaseHas('lootcouncil_items', [
            'id' => 28453,
            'name' => null,
        ]);
    }

    #[Test]
    public function seeder_still_creates_items_when_media_api_returns_empty_array(): void
    {
        $this->mock(BlizzardService::class, function (MockInterface $mock) {
            $mock->shouldReceive('findItem')
                ->andReturnUsing(fn (int $id) => $this->makeItemResponse($id));

            $mock->shouldReceive('getItemMedia')
                ->andReturn([]);
        });

        $this->seedWithLimitedItems();

        $item = Item::find(28453);

        $this->assertNotNull($item);
        $this->assertSame('Item 28453', $item->name);
        $this->assertNull($item->icon);
    }

    #[Test]
    public function seeder_sets_correct_raid_and_boss_ids_from_static_data(): void
    {
        $this->mockBlizzardService();

        $this->seedWithLimitedItems();

        $this->assertDatabaseHas('lootcouncil_items', [
            'id' => 28453,
            'raid_id' => 1,
            'boss_id' => 1,
        ]);
    }

    #[Test]
    public function seeder_skips_item_and_continues_when_api_throws_http_exception(): void
    {
        $this->mock(BlizzardService::class, function (MockInterface $mock) {
            $mock->shouldReceive('findItem')
                ->with(28453)
                ->andThrow(new ConnectionException('Connection refused'));

            $mock->shouldReceive('findItem')
                ->andReturnUsing(fn (int $id) => $this->makeItemResponse($id));

            $mock->shouldReceive('getItemMedia')
                ->andReturnUsing(fn (int $id) => $this->makeMediaResponse($id));
        });

        $this->seedWithLimitedItems();

        // The failed item is still created (updateOrCreate ran before the API call) but has no name/icon
        $this->assertDatabaseHas('lootcouncil_items', ['id' => 28453, 'name' => null]);
        // Other items still get name and icon
        $this->assertDatabaseHas('lootcouncil_items', ['id' => 28454, 'name' => 'Item 28454']);
    }
}
