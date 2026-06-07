<?php

namespace Tests\Feature\Database\Seeders;

use App\Enums\ItemQuality;
use App\Http\Integrations\Blizzard\Requests\Item\GetItemMediaRequest;
use App\Http\Integrations\Blizzard\Requests\Item\GetItemRequest;
use App\Http\Integrations\Blizzard\Requests\Render\FetchIconRequest;
use App\Jobs\AttachBlizzardIconToModel;
use App\Models\DailyQuest;
use App\Models\Item;
use Database\Seeders\DailyQuestSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\PendingRequest;
use Saloon\Laravel\Facades\Saloon;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\TestCase;

class DailyQuestSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->fakeSaloon();
    }

    #[Test]
    public function seeder_creates_quests_of_each_type(): void
    {
        $this->runSeeder();

        $this->assertDatabaseHas('daily_quests', ['type' => 'Cooking']);
        $this->assertDatabaseHas('daily_quests', ['type' => 'Fishing']);
        $this->assertDatabaseHas('daily_quests', ['type' => 'Normal dungeon']);
        $this->assertDatabaseHas('daily_quests', ['type' => 'Heroic dungeon']);
        $this->assertDatabaseHas('daily_quests', ['type' => 'PvP battleground']);
    }

    #[Test]
    public function seeder_attaches_correct_icon_per_type_mode(): void
    {
        $this->runSeeder();

        $cooking = DailyQuest::where('type', 'Cooking')->first();
        $fishing = DailyQuest::where('type', 'Fishing')->first();
        $dungeonNormal = DailyQuest::where('type', 'Normal dungeon')->first();
        $dungeonHeroic = DailyQuest::where('type', 'Heroic dungeon')->first();
        $pvp = DailyQuest::where('type', 'PvP battleground')->first();

        $this->assertSame('inv_misc_food_15.jpg', $cooking->getFirstMedia('blizzard_icons')->file_name);
        $this->assertSame('trade_fishing.jpg', $fishing->getFirstMedia('blizzard_icons')->file_name);
        $this->assertSame('inv_qiraj_jewelencased.jpg', $dungeonNormal->getFirstMedia('blizzard_icons')->file_name);
        $this->assertSame('spell_holy_championsbond.jpg', $dungeonHeroic->getFirstMedia('blizzard_icons')->file_name);
        $this->assertSame('inv_bannerpvp_02.jpg', $pvp->getFirstMedia('blizzard_icons')->file_name);

        $this->assertSame(56, $cooking->getFirstMedia('blizzard_icons')->getCustomProperty('size'));
        Storage::disk('public')->assertExists('blizzard-cdn/icons/56/inv_misc_food_15.jpg');
    }

    #[Test]
    public function seeder_is_idempotent_and_does_not_reattach_icons(): void
    {
        $this->runSeeder();

        $questCount = DailyQuest::count();
        $mediaCount = Media::count();

        $this->runSeeder();

        $this->assertSame($questCount, DailyQuest::count());
        $this->assertSame($mediaCount, Media::count());
    }

    #[Test]
    public function seeder_creates_reward_items_that_do_not_yet_exist(): void
    {
        $this->assertDatabaseMissing('items', ['id' => 33844]);

        $this->runSeeder();

        $this->assertDatabaseHas('items', ['id' => 33844, 'name' => 'Item 33844']);
        $this->assertSame(ItemQuality::UNCOMMON, Item::find(33844)->quality);
        $this->assertTrue(Item::find(33844)->hasMedia('blizzard_icons'));
    }

    #[Test]
    public function seeder_does_not_refetch_reward_items_that_already_exist(): void
    {
        Item::withoutEvents(fn () => Item::forceCreate([
            'id' => 33844,
            'name' => 'Existing Barrel',
            'quality' => ItemQuality::COMMON->value,
        ]));

        $this->runSeeder();

        $this->assertDatabaseHas('items', ['id' => 33844, 'name' => 'Existing Barrel']);
    }

    #[Test]
    public function seeder_syncs_pivot_with_correct_quantity(): void
    {
        $this->runSeeder();

        $heroic = DailyQuest::where('type', 'Heroic dungeon')->first();

        $this->assertTrue($heroic->rewards->contains('id', 29434));
        $this->assertSame(2, (int) $heroic->rewards->firstWhere('id', 29434)->pivot->quantity);
    }

    #[Test]
    public function seeder_is_idempotent_for_pivot_and_items(): void
    {
        $this->runSeeder();

        $itemCount = Item::count();
        $pivotCount = \DB::table('pivot_dailyquest_rewards')->count();

        $this->runSeeder();

        $this->assertSame($itemCount, Item::count());
        $this->assertSame($pivotCount, \DB::table('pivot_dailyquest_rewards')->count());
    }

    #[Test]
    public function seeder_dispatches_retry_job_when_reward_item_icon_returns_403(): void
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
                if (str_contains($request->getUrl(), 'item_33844.jpg')) {
                    return MockResponse::make(body: ['code' => 403, 'detail' => 'Forbidden'], status: 403);
                }

                return MockResponse::make(body: 'BINARY', status: 200);
            },
        ]);

        $this->runSeeder();

        $this->assertNotNull(Item::find(33844));
        $this->assertFalse(Item::find(33844)->hasMedia('blizzard_icons'));

        Queue::assertPushed(AttachBlizzardIconToModel::class, function (AttachBlizzardIconToModel $job) {
            return $job->modelClass === Item::class && $job->modelKey === 33844;
        });
    }

    #[Test]
    public function seeder_only_fetches_shared_reward_item_once_across_multiple_quests(): void
    {
        $requestCount = 0;

        Saloon::fake([
            'eu.battle.net/oauth/token' => MockResponse::make(
                body: ['access_token' => 'test_token', 'token_type' => 'bearer', 'expires_in' => 3600],
                status: 200,
            ),
            GetItemRequest::class => function (PendingRequest $request) use (&$requestCount): MockResponse {
                if ($this->extractItemIdFromRequest($request) === 33844) {
                    $requestCount++;
                }

                return MockResponse::make(body: $this->makeItemResponse($this->extractItemIdFromRequest($request)), status: 200);
            },
            GetItemMediaRequest::class => function (PendingRequest $request): MockResponse {
                return MockResponse::make(body: $this->makeMediaResponse($this->extractItemIdFromRequest($request)), status: 200);
            },
            FetchIconRequest::class => MockResponse::make(body: 'BINARY', status: 200),
        ]);

        $this->runSeeder();

        // Item 33844 (Barrel of Fish) is a reward for all 4 cooking quests — should only be fetched once
        $this->assertSame(1, $requestCount);
    }

    #[Test]
    public function seeder_releases_all_locks_after_run(): void
    {
        $this->runSeeder();

        // All item locks should be released — a fresh lock on any reward item should succeed
        $this->assertTrue(Cache::lock('daily-quest-seeder-item-33844', 1)->get());
        $this->assertTrue(Cache::lock('daily-quest-seeder-item-29434', 1)->get());
    }

    #[Test]
    public function seeder_dispatches_retry_job_when_type_icon_returns_403(): void
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
                if (str_contains($request->getUrl(), 'trade_fishing.jpg')) {
                    return MockResponse::make(body: ['code' => 403, 'detail' => 'Forbidden'], status: 403);
                }

                return MockResponse::make(body: 'BINARY', status: 200);
            },
        ]);

        $this->runSeeder();

        $fishing = DailyQuest::where('type', 'Fishing')->first();
        $this->assertFalse($fishing->hasMedia('blizzard_icons'));

        Queue::assertPushed(AttachBlizzardIconToModel::class, function (AttachBlizzardIconToModel $job) {
            return $job->modelClass === DailyQuest::class
                && str_contains($job->assetUrl, 'trade_fishing.jpg');
        });
    }

    /** @return array<string, mixed> */
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

    /** @return array{id: int, assets: array<int, array{key: string, value: string, file_data_id: int}>} */
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
        return (int) last(explode('/', (string) parse_url($request->getUrl(), PHP_URL_PATH)));
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

    private function runSeeder(): void
    {
        Model::unguarded(fn (): mixed => app(DailyQuestSeeder::class)->run());
    }
}
