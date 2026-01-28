<?php

namespace Tests\Feature\Loot;

use App\Models\LootCouncil\Item;
use App\Models\LootCouncil\Priority;
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

class ItemEditTest extends TestCase
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

    public function test_edit_item_requires_authentication(): void
    {
        $item = $this->createTestItem();

        $response = $this->get(route('loot.items.edit', $item));

        $response->assertRedirect('/login');
    }

    public function test_edit_item_forbids_guest_users(): void
    {
        $user = User::factory()->guest()->create();
        $item = $this->createTestItem();

        $response = $this->actingAs($user)->get(route('loot.items.edit', $item));

        $response->assertForbidden();
    }

    public function test_edit_item_forbids_member_users(): void
    {
        $user = User::factory()->member()->create();
        $item = $this->createTestItem();

        $response = $this->actingAs($user)->get(route('loot.items.edit', $item));

        $response->assertForbidden();
    }

    public function test_edit_item_forbids_raider_users(): void
    {
        $user = User::factory()->raider()->create();
        $item = $this->createTestItem();

        $response = $this->actingAs($user)->get(route('loot.items.edit', $item));

        $response->assertForbidden();
    }

    public function test_edit_item_allows_officer_users(): void
    {
        $user = User::factory()->officer()->create();
        $item = $this->createTestItem();

        $response = $this->actingAs($user)->get(route('loot.items.edit', $item));

        $response->assertOk();
    }

    public function test_edit_item_returns_item_and_all_priorities(): void
    {
        $user = User::factory()->officer()->create();
        $item = $this->createTestItem();
        $priority1 = Priority::factory()->role()->create(['title' => 'Tank']);
        $priority2 = Priority::factory()->role()->create(['title' => 'Healer']);
        $priority3 = Priority::factory()->role()->create(['title' => 'DPS']);

        $item->priorities()->attach($priority1->id, ['weight' => 0]);
        $item->priorities()->attach($priority2->id, ['weight' => 1]);

        $response = $this->actingAs($user)->get(route('loot.items.edit', $item));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Loot/ItemEdit')
            ->has('item.data')
            ->has('item.data.priorities', 2)
            ->has('allPriorities.data', 3)
        );
    }

    public function test_update_priorities_requires_authentication(): void
    {
        $item = $this->createTestItem();

        $response = $this->put(route('loot.items.priorities.update', $item), [
            'priorities' => [],
        ]);

        $response->assertRedirect('/login');
    }

    public function test_update_priorities_forbids_non_officers(): void
    {
        $user = User::factory()->member()->create();
        $item = $this->createTestItem();

        $response = $this->actingAs($user)->put(route('loot.items.priorities.update', $item), [
            'priorities' => [],
        ]);

        $response->assertForbidden();
    }

    public function test_update_priorities_allows_officers(): void
    {
        $user = User::factory()->officer()->create();
        $item = $this->createTestItem();
        $priority = Priority::factory()->create();

        $response = $this->actingAs($user)->put(route('loot.items.priorities.update', $item), [
            'priorities' => [
                ['priority_id' => $priority->id, 'weight' => 0],
            ],
        ]);

        $response->assertRedirect(route('loot.items.show', $item));
    }

    public function test_update_priorities_syncs_correctly(): void
    {
        $user = User::factory()->officer()->create();
        $item = $this->createTestItem();
        $priority1 = Priority::factory()->create();
        $priority2 = Priority::factory()->create();
        $priority3 = Priority::factory()->create();

        $item->priorities()->attach($priority1->id, ['weight' => 0]);

        $response = $this->actingAs($user)->put(route('loot.items.priorities.update', $item), [
            'priorities' => [
                ['priority_id' => $priority2->id, 'weight' => 0],
                ['priority_id' => $priority3->id, 'weight' => 1],
            ],
        ]);

        $response->assertRedirect(route('loot.items.show', $item));

        $item->refresh();
        $this->assertCount(2, $item->priorities);
        $this->assertTrue($item->priorities->contains('id', $priority2->id));
        $this->assertTrue($item->priorities->contains('id', $priority3->id));
        $this->assertFalse($item->priorities->contains('id', $priority1->id));
    }

    public function test_update_priorities_validates_priority_ids(): void
    {
        $user = User::factory()->officer()->create();
        $item = $this->createTestItem();

        $response = $this->actingAs($user)->put(route('loot.items.priorities.update', $item), [
            'priorities' => [
                ['priority_id' => 99999, 'weight' => 0],
            ],
        ]);

        $response->assertSessionHasErrors(['priorities.0.priority_id']);
    }

    public function test_update_priorities_validates_weights(): void
    {
        $user = User::factory()->officer()->create();
        $item = $this->createTestItem();
        $priority = Priority::factory()->create();

        $response = $this->actingAs($user)->put(route('loot.items.priorities.update', $item), [
            'priorities' => [
                ['priority_id' => $priority->id, 'weight' => -1],
            ],
        ]);

        $response->assertSessionHasErrors(['priorities.0.weight']);
    }

    public function test_update_priorities_handles_empty_array(): void
    {
        $user = User::factory()->officer()->create();
        $item = $this->createTestItem();
        $priority = Priority::factory()->create();

        $item->priorities()->attach($priority->id, ['weight' => 0]);

        $response = $this->actingAs($user)->put(route('loot.items.priorities.update', $item), [
            'priorities' => [],
        ]);

        $response->assertRedirect(route('loot.items.show', $item));

        $item->refresh();
        $this->assertCount(0, $item->priorities);
    }

    public function test_update_priorities_handles_same_weight_priorities(): void
    {
        $user = User::factory()->officer()->create();
        $item = $this->createTestItem();
        $priority1 = Priority::factory()->create();
        $priority2 = Priority::factory()->create();

        $response = $this->actingAs($user)->put(route('loot.items.priorities.update', $item), [
            'priorities' => [
                ['priority_id' => $priority1->id, 'weight' => 0],
                ['priority_id' => $priority2->id, 'weight' => 0],
            ],
        ]);

        $response->assertRedirect(route('loot.items.show', $item));

        $item->refresh();
        $this->assertCount(2, $item->priorities);

        $weights = $item->priorities->pluck('pivot.weight')->toArray();
        $this->assertEquals([0, 0], $weights);
    }
}
