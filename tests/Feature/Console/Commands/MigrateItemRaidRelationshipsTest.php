<?php

namespace Tests\Feature\Console\Commands;

use App\Http\Integrations\Blizzard\Requests\Item\GetItemMediaRequest;
use App\Http\Integrations\Blizzard\Requests\Item\GetItemRequest;
use App\Http\Integrations\Blizzard\Requests\Render\FetchIconRequest;
use App\Models\Item;
use Database\Seeders\BossSeeder;
use Database\Seeders\PhaseSeeder;
use Database\Seeders\RaidSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\PendingRequest;
use Saloon\Laravel\Facades\Saloon;
use Tests\Support\Database\Seeders\LimitsItemSeederFixtures;
use Tests\TestCase;

#[Group('loot')]
class MigrateItemRaidRelationshipsTest extends TestCase
{
    use LimitsItemSeederFixtures;
    use RefreshDatabase;

    /**
     * Item ids the ItemSeeder step is limited to, so the command's tests
     * don't process all 702 real items. Includes 28453 (used by the
     * skip-on-404 test) and the cross-raid trash items asserted on by
     * it_links_the_cross_raid_trash_items_to_both_raids().
     *
     * @var array<int, int>
     */
    private const array LIMITED_ITEM_IDS = [28453, 32589, 32590, 32591, 32592, 32609, 34009];

    /**
     * This test runs real migrations mid-test (DDL), which triggers an
     * implicit commit under MySQL/MariaDB and desyncs Laravel's savepoint
     * bookkeeping if the test is wrapped in RefreshDatabase's usual
     * transaction. Disabling transactional wrapping avoids that, at the
     * cost of needing to explicitly restore the schema in tearDown().
     *
     * @var array<int, string>
     */
    protected array $connectionsToTransact = [];

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->seed([PhaseSeeder::class, RaidSeeder::class, BossSeeder::class]);

        $this->fakeSaloon();

        $this->limitItemSeederTo(self::LIMITED_ITEM_IDS);
    }

    protected function tearDown(): void
    {
        $this->artisan('migrate:fresh')->run();

        parent::tearDown();
    }

    #[Test]
    public function it_has_the_correct_signature(): void
    {
        $this->artisan('app:migrate-item-raid-relationships --help')
            ->expectsOutputToContain('app:migrate-item-raid-relationships')
            ->assertExitCode(0);
    }

    #[Test]
    #[Group('happy-path')]
    public function it_runs_both_migrations_and_the_seeder_in_one_sweep(): void
    {
        $this->rewindBothMigrations();

        $this->artisan('app:migrate-item-raid-relationships')
            ->expectsOutputToContain('Creating pivot_items_raids')
            ->expectsOutputToContain('Seeding items')
            ->expectsOutputToContain('Dropping items.raid_id')
            ->assertExitCode(0);

        $this->assertTrue(Schema::hasTable('pivot_items_raids'));
        $this->assertFalse(Schema::hasColumn('items', 'raid_id'));
        $this->assertGreaterThan(0, DB::table('pivot_items_raids')->count());
    }

    #[Test]
    #[Group('happy-path')]
    public function it_links_the_cross_raid_trash_items_to_both_raids(): void
    {
        $this->rewindBothMigrations();

        $this->artisan('app:migrate-item-raid-relationships')->assertExitCode(0);

        foreach ([32589, 32590, 32591, 32592, 32609, 34009] as $itemId) {
            $this->assertEqualsCanonicalizing(
                [6, 7],
                Item::find($itemId)->raids()->pluck('raids.id')->all(),
                "Item {$itemId} should drop in both Black Temple and Hyjal Summit."
            );
        }
    }

    #[Test]
    #[Group('failure-path')]
    public function it_refuses_to_run_when_the_pivot_migration_has_already_been_applied(): void
    {
        $this->rewindBothMigrations();

        DB::table('migrations')->insert([
            'migration' => '2026_08_15_100000_create_pivot_items_raids_table',
            'batch' => 99,
        ]);

        $this->artisan('app:migrate-item-raid-relationships')
            ->expectsOutputToContain('2026_08_15_100000_create_pivot_items_raids_table')
            ->assertExitCode(1);
    }

    #[Test]
    #[Group('failure-path')]
    public function it_refuses_to_run_when_the_drop_migration_has_already_been_applied(): void
    {
        $this->rewindBothMigrations();

        DB::table('migrations')->insert([
            'migration' => '2026_08_15_100001_drop_raid_id_from_items_table',
            'batch' => 99,
        ]);

        $this->artisan('app:migrate-item-raid-relationships')
            ->expectsOutputToContain('2026_08_15_100001_drop_raid_id_from_items_table')
            ->assertExitCode(1);
    }

    #[Test]
    #[Group('failure-path')]
    public function it_leaves_the_raid_id_column_intact_when_it_aborts(): void
    {
        $this->rewindBothMigrations();

        DB::table('migrations')->insert([
            'migration' => '2026_08_15_100001_drop_raid_id_from_items_table',
            'batch' => 99,
        ]);

        $this->artisan('app:migrate-item-raid-relationships')->assertExitCode(1);

        $this->assertTrue(Schema::hasColumn('items', 'raid_id'));
    }

    #[Test]
    #[Group('failure-path')]
    public function it_aborts_before_dropping_raid_id_when_the_seeder_skips_an_item(): void
    {
        $this->rewindBothMigrations();

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

        $this->artisan('app:migrate-item-raid-relationships')
            ->expectsOutputToContain('28453')
            ->assertExitCode(1);

        $this->assertTrue(Schema::hasColumn('items', 'raid_id'));

        $this->assertTrue(
            DB::table('migrations')->where('migration', '2026_08_15_100000_create_pivot_items_raids_table')->exists()
        );
        $this->assertFalse(
            DB::table('migrations')->where('migration', '2026_08_15_100001_drop_raid_id_from_items_table')->exists()
        );
    }

    // ↓ Helpers

    /**
     * Return the database to the pre-migration state the command expects.
     *
     * RefreshDatabase has already run every migration, so both files are
     * recorded as applied and the schema is at its final shape. Roll the two
     * back so the command has real work to do.
     *
     * Targeted by --path rather than --step, since --step rolls back the
     * last N migrations by run order, which silently breaks if any other
     * migration is ever added with a later timestamp than these two.
     */
    private function rewindBothMigrations(): void
    {
        $this->artisan('migrate:rollback', [
            '--path' => [
                'database/migrations/2026_08_15_100000_create_pivot_items_raids_table.php',
                'database/migrations/2026_08_15_100001_drop_raid_id_from_items_table.php',
            ],
        ])->assertExitCode(0);

        DB::table('migrations')
            ->whereIn('migration', [
                '2026_08_15_100000_create_pivot_items_raids_table',
                '2026_08_15_100001_drop_raid_id_from_items_table',
            ])
            ->delete();
    }

    private function extractItemIdFromRequest(PendingRequest $request): int
    {
        return (int) last(explode('/', parse_url($request->getUrl(), PHP_URL_PATH)));
    }

    /**
     * Returns a correctly-shaped Blizzard item response.
     *
     * @return array<string, mixed>
     */
    private function makeItemResponse(int $id): array
    {
        return [
            'id' => $id,
            'name' => "Item {$id}",
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

    /**
     * Fake Saloon so the command's ItemSeeder step can fetch every item's
     * name and icon from the Blizzard API without a real network call.
     *
     * Mirrors ItemSeederTest::fakeSaloon() — a blanket `Saloon::fake([])`
     * is not enough because the command runs the full, unfiltered
     * ItemSeeder, which fetches an item and media response for every row.
     */
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
}
