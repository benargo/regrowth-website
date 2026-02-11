<?php

namespace Tests\Feature\LootCouncil;

use App\Models\LootCouncil\Item;
use App\Models\TBC\Boss;
use App\Models\TBC\Phase;
use App\Models\TBC\Raid;
use App\Models\User;
use App\Services\Blizzard\ItemService;
use App\Services\Blizzard\MediaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class ItemShowTest extends TestCase
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
                $mock->shouldReceive('find')
                    ->andReturn([
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

    public function test_show_item_requires_authentication(): void
    {
        $item = $this->createTestItem();

        $response = $this->get(route('loot.items.show', ['item' => $item->id, 'name' => 'test-item-'.$item->id]));

        $response->assertRedirect('/login');
    }

    public function test_show_item_forbids_guest_users(): void
    {
        $user = User::factory()->guest()->create();
        $item = $this->createTestItem();

        $response = $this->actingAs($user)->get(route('loot.items.show', ['item' => $item->id, 'name' => 'test-item-'.$item->id]));

        $response->assertForbidden();
    }

    public function test_show_item_allows_member_users(): void
    {
        $user = User::factory()->member()->create();
        $item = $this->createTestItem();

        $response = $this->actingAs($user)->get(route('loot.items.show', ['item' => $item->id, 'name' => 'test-item-'.$item->id]));

        $response->assertOk();
    }

    public function test_show_item_allows_raider_users(): void
    {
        $user = User::factory()->raider()->create();
        $item = $this->createTestItem();

        $response = $this->actingAs($user)->get(route('loot.items.show', ['item' => $item->id, 'name' => 'test-item-'.$item->id]));

        $response->assertOk();
    }

    public function test_show_item_allows_officer_users(): void
    {
        $user = User::factory()->officer()->create();
        $item = $this->createTestItem();

        $response = $this->actingAs($user)->get(route('loot.items.show', ['item' => $item->id, 'name' => 'test-item-'.$item->id]));

        $response->assertOk();
    }

    public function test_show_item_redirects_from_null_slug_to_correct_slug(): void
    {
        $user = User::factory()->member()->create();
        $item = $this->createTestItem();

        $response = $this->actingAs($user)->get(route('loot.items.show', ['item' => $item->id]));

        $response->assertRedirect(route('loot.items.show', ['item' => $item->id, 'name' => 'test-item-'.$item->id]));
        $response->assertStatus(303);
    }

    public function test_show_item_redirects_from_incorrect_slug_to_correct_slug(): void
    {
        $user = User::factory()->member()->create();
        $item = $this->createTestItem();

        $response = $this->actingAs($user)->get(route('loot.items.show', ['item' => $item->id, 'name' => 'wrong-slug']));

        $response->assertRedirect(route('loot.items.show', ['item' => $item->id, 'name' => 'test-item-'.$item->id]));
        $response->assertStatus(303);
    }

    public function test_show_item_renders_with_correct_slug(): void
    {
        $user = User::factory()->member()->create();
        $item = $this->createTestItem();

        $response = $this->actingAs($user)->get(route('loot.items.show', ['item' => $item->id, 'name' => 'test-item-'.$item->id]));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('LootBiasTool/ItemShow')
            ->has('item.data')
        );
    }

    public function test_show_item_uses_fallback_slug_when_api_returns_no_name(): void
    {
        $user = User::factory()->member()->create();
        $item = $this->createTestItem();

        // Override the mock to return no name
        $this->instance(
            ItemService::class,
            Mockery::mock(ItemService::class, function (MockInterface $mock) {
                $mock->shouldReceive('find')
                    ->andReturn(['id' => 123]);
            })
        );

        $response = $this->actingAs($user)->get(route('loot.items.show', ['item' => $item->id]));

        $response->assertRedirect(route('loot.items.show', ['item' => $item->id, 'name' => 'item-'.$item->id]));
        $response->assertStatus(303);
    }

    public function test_show_item_renders_with_fallback_slug_when_api_returns_no_name(): void
    {
        $user = User::factory()->member()->create();
        $item = $this->createTestItem();

        // Override the mock to return no name
        $this->instance(
            ItemService::class,
            Mockery::mock(ItemService::class, function (MockInterface $mock) use ($item) {
                $mock->shouldReceive('find')
                    ->andReturn(['id' => $item->id]);
            })
        );

        $response = $this->actingAs($user)->get(route('loot.items.show', ['item' => $item->id, 'name' => 'item-'.$item->id]));

        $response->assertOk();
    }
}
