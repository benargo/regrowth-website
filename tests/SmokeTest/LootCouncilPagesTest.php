<?php

namespace Tests\SmokeTest;

use App\Http\Integrations\Blizzard\Requests\Item\GetItemMediaRequest;
use App\Http\Integrations\Blizzard\Requests\Item\GetItemRequest;
use App\Http\Integrations\Blizzard\Requests\Render\FetchAssetRequest;
use App\Models\Boss;
use App\Models\DiscordRole;
use App\Models\LootCouncil\Item;
use App\Models\Permission;
use App\Models\Phase;
use App\Models\Raid;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\PendingRequest;
use Saloon\Laravel\Facades\Saloon;
use Tests\TestCase;

class LootCouncilPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockBlizzardServices();

        $viewLootBiasTool = Permission::firstOrCreate(['name' => 'view-loot-bias-tool', 'guard_name' => 'web']);
        $viewAllComments = Permission::firstOrCreate(['name' => 'view-all-comments', 'guard_name' => 'web']);
        $editItems = Permission::firstOrCreate(['name' => 'edit-items', 'guard_name' => 'web']);

        DiscordRole::firstOrCreate(['id' => '829022020301094922'], ['name' => 'Member', 'position' => 2, 'is_visible' => true])->givePermissionTo($viewLootBiasTool);

        $officerRole = DiscordRole::firstOrCreate(['id' => '829021769448816691'], ['name' => 'Officer', 'position' => 6, 'is_visible' => true]);
        $officerRole->givePermissionTo($viewLootBiasTool);
        $officerRole->givePermissionTo($viewAllComments);
        $officerRole->givePermissionTo($editItems);
    }

    protected function mockBlizzardServices(): void
    {
        Storage::fake('public');

        Saloon::fake([
            'eu.battle.net/oauth/token' => MockResponse::make([
                'access_token' => 'test_token',
                'token_type' => 'bearer',
                'expires_in' => 3600,
            ]),
            GetItemRequest::class => function (PendingRequest $pendingRequest): MockResponse {
                $path = parse_url($pendingRequest->getUrl(), PHP_URL_PATH) ?: '';
                $segments = explode('/', trim($path, '/'));
                $itemId = (int) ($segments[array_key_last($segments)] ?? 0);

                return MockResponse::make(body: [
                    'id' => $itemId,
                    'name' => "Test Item {$itemId}",
                    'quality' => ['type' => 'UNCOMMON', 'name' => 'Uncommon'],
                    'level' => 1,
                    'required_level' => 1,
                    'media' => ['key' => ['href' => "https://example.test/media/{$itemId}"]],
                    'item_class' => ['key' => ['href' => 'https://example.test/item-class/2'], 'name' => 'Weapon', 'id' => 2],
                    'item_subclass' => ['key' => ['href' => 'https://example.test/item-subclass/2-7'], 'name' => 'Sword', 'id' => 7],
                    'inventory_type' => ['type' => 'WEAPONMAINHAND', 'name' => 'Main Hand'],
                    'purchase_price' => 0,
                    'sell_price' => 0,
                ], status: 200);
            },
            GetItemMediaRequest::class => MockResponse::make(body: [
                'id' => 0,
                'assets' => [
                    [
                        'key' => 'icon',
                        'value' => 'https://render.worldofwarcraft.com/eu/icons/56/inv_misc_questionmark.jpg',
                        'file_data_id' => 123,
                    ],
                ],
            ], status: 200),
            FetchAssetRequest::class => MockResponse::make(body: 'BINARY', status: 200),
        ]);
    }

    protected function createTestItem(): Item
    {
        $phase = Phase::factory()->started()->create();
        $raid = Raid::factory()->create(['phase_id' => $phase->id]);
        $boss = Boss::factory()->create(['raid_id' => $raid->id]);

        return Item::factory()->create(['raid_id' => $raid->id, 'boss_id' => $boss->id]);
    }

    #[Test]
    public function loot_index_loads(): void
    {
        $user = User::factory()->member()->create();
        $phase = Phase::factory()->started()->create();
        Raid::factory()->create(['phase_id' => $phase->id]);

        $response = $this->actingAs($user)->get('/loot');

        $response->assertOk();
        $response->assertSee('Regrowth');
    }

    #[Test]
    public function loot_raid_page_loads(): void
    {
        $user = User::factory()->member()->create();
        $phase = Phase::factory()->started()->create();
        $raid = Raid::factory()->create(['phase_id' => $phase->id]);

        $response = $this->actingAs($user)->get(route('loot.raids.show', ['raid' => $raid->id, 'name' => Str::slug($raid->name)]));

        $response->assertOk();
        $response->assertSee('Regrowth');
    }

    #[Test]
    public function loot_comments_page_loads(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get('/loot/comments');

        $response->assertOk();
        $response->assertSee('Regrowth');
    }

    #[Test]
    public function loot_item_show_page_loads(): void
    {
        $user = User::factory()->member()->create();
        $item = $this->createTestItem();

        $response = $this->actingAs($user)->get(route('loot.items.show', [
            'item' => $item->id,
            'name' => 'test-item-'.$item->id,
        ]));

        $response->assertOk();
        $response->assertSee('Regrowth');
    }

    #[Test]
    public function loot_item_edit_page_loads(): void
    {
        $user = User::factory()->officer()->create();
        $item = $this->createTestItem();

        $response = $this->actingAs($user)->get(route('loot.items.edit', [
            'item' => $item->id,
            'name' => 'test-item-'.$item->id,
        ]));

        $response->assertOk();
        $response->assertSee('Regrowth');
    }
}
