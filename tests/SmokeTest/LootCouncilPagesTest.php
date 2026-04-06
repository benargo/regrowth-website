<?php

namespace Tests\SmokeTest;

use App\Models\DiscordRole;
use App\Models\LootCouncil\Item;
use App\Models\Permission;
use App\Models\TBC\Boss;
use App\Models\TBC\Phase;
use App\Models\TBC\Raid;
use App\Models\User;
use App\Services\Blizzard\BlizzardService;
use App\Services\Blizzard\MediaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
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
        $this->instance(
            BlizzardService::class,
            Mockery::mock(BlizzardService::class, function (MockInterface $mock) {
                $mock->shouldReceive('findItem')
                    ->andReturnUsing(fn (int $id) => [
                        'id' => $id,
                        'name' => "Test Item {$id}",
                    ]);
                $mock->shouldReceive('findMedia')
                    ->with('item', Mockery::any())
                    ->andReturn([
                        'assets' => [
                            ['key' => 'icon', 'value' => 'https://example.com/icon.jpg', 'file_data_id' => 123],
                        ],
                    ]);
            })
        );

        $this->instance(
            MediaService::class,
            Mockery::mock(MediaService::class, function (MockInterface $mock) {
                $mock->shouldReceive('get')
                    ->andReturn([123 => 'https://example.com/icon.jpg']);
            })
        );
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

        $response->assertRedirect();
    }

    #[Test]
    public function loot_phase_page_loads(): void
    {
        $user = User::factory()->member()->create();
        $phase = Phase::factory()->started()->create();
        Raid::factory()->create(['phase_id' => $phase->id]);

        $response = $this->actingAs($user)->get(route('loot.phase', ['phase' => $phase->id]));

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
