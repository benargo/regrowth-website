<?php

namespace Tests\SmokeTest;

use App\Models\LootCouncil\Item;
use App\Models\TBC\Boss;
use App\Models\TBC\Phase;
use App\Models\TBC\Raid;
use App\Models\User;
use App\Services\Blizzard\ItemService;
use App\Services\Blizzard\MediaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class LootCouncilPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockBlizzardServices();
    }

    protected function mockBlizzardServices(): void
    {
        $this->instance(
            ItemService::class,
            Mockery::mock(ItemService::class, function (MockInterface $mock) {
                $mock->shouldReceive('find')
                    ->andReturnUsing(fn (int $id) => [
                        'id' => $id,
                        'name' => "Test Item {$id}",
                    ]);
            })
        );

        $this->instance(
            MediaService::class,
            Mockery::mock(MediaService::class, function (MockInterface $mock) {
                $mock->shouldReceive('find')->andReturn([
                    'assets' => [
                        ['key' => 'icon', 'value' => 'https://example.com/icon.jpg', 'file_data_id' => 123],
                    ],
                ]);
                $mock->shouldReceive('getAssetUrls')
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

    public function test_loot_index_loads(): void
    {
        $user = User::factory()->member()->create();
        $phase = Phase::factory()->started()->create();
        Raid::factory()->create(['phase_id' => $phase->id]);

        $response = $this->actingAs($user)->get('/loot');

        $response->assertRedirect();
    }

    public function test_loot_phase_page_loads(): void
    {
        $user = User::factory()->member()->create();
        $phase = Phase::factory()->started()->create();
        Raid::factory()->create(['phase_id' => $phase->id]);

        $response = $this->actingAs($user)->get(route('loot.phase', ['phase' => $phase->id]));

        $response->assertOk();
        $response->assertSee('Regrowth');
    }

    public function test_loot_comments_page_loads(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get('/loot/comments');

        $response->assertOk();
        $response->assertSee('Regrowth');
    }

    public function test_loot_item_show_page_loads(): void
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

    public function test_loot_item_edit_page_loads(): void
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
