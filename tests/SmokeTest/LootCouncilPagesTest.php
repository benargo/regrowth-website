<?php

namespace Tests\SmokeTest;

use App\Http\Integrations\Blizzard\Requests\Item\GetItemMediaRequest;
use App\Http\Integrations\Blizzard\Requests\Render\FetchAssetRequest;
use App\Models\Boss;
use App\Models\DiscordRole;
use App\Models\LootCouncil\Item;
use App\Models\Permission;
use App\Models\Phase;
use App\Models\Raid;
use App\Models\User;
use App\Services\Blizzard\BlizzardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Http\Faking\MockResponse;
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

        $this->instance(
            BlizzardService::class,
            Mockery::mock(BlizzardService::class, function (MockInterface $mock) {
                $mock->shouldReceive('findItem')
                    ->andReturnUsing(fn (int $id) => [
                        'id' => $id,
                        'name' => "Test Item {$id}",
                    ]);
            })
        );

        Saloon::fake([
            'eu.battle.net/oauth/token' => MockResponse::make([
                'access_token' => 'test_token',
                'token_type' => 'bearer',
                'expires_in' => 3600,
            ]),
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
