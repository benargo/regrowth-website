<?php

namespace Tests\Feature\LootCouncil;

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

    /**
     * Generate the edit URL with the name in the correct path position.
     * The route helper puts optional parameters in query string, but we need it in the path.
     */
    protected function editUrl(Item $item, ?string $name = null): string
    {
        $slug = $name ?? 'test-item-'.$item->id;

        return "/loot/items/{$item->id}/{$slug}/edit";
    }

    public function test_edit_item_requires_authentication(): void
    {
        $item = $this->createTestItem();

        $response = $this->get($this->editUrl($item));

        $response->assertRedirect('/login');
    }

    public function test_edit_item_forbids_guest_users(): void
    {
        $user = User::factory()->guest()->create();
        $item = $this->createTestItem();

        $response = $this->actingAs($user)->get($this->editUrl($item));

        $response->assertForbidden();
    }

    public function test_edit_item_forbids_member_users(): void
    {
        $user = User::factory()->member()->create();
        $item = $this->createTestItem();

        $response = $this->actingAs($user)->get($this->editUrl($item));

        $response->assertForbidden();
    }

    public function test_edit_item_forbids_raider_users(): void
    {
        $user = User::factory()->raider()->create();
        $item = $this->createTestItem();

        $response = $this->actingAs($user)->get($this->editUrl($item));

        $response->assertForbidden();
    }

    public function test_edit_item_allows_officer_users(): void
    {
        $user = User::factory()->officer()->create();
        $item = $this->createTestItem();

        $response = $this->actingAs($user)->get($this->editUrl($item));

        $response->assertOk();
    }

    public function test_edit_item_redirects_from_incorrect_slug_to_correct_slug(): void
    {
        $user = User::factory()->officer()->create();
        $item = $this->createTestItem();

        $response = $this->actingAs($user)->get($this->editUrl($item, 'wrong-slug'));

        // The controller uses route() which generates query string format, so we match that
        $response->assertRedirect(route('loot.items.edit', ['item' => $item->id, 'name' => 'test-item-'.$item->id]));
        $response->assertStatus(303);
    }

    public function test_edit_item_renders_with_correct_slug(): void
    {
        $user = User::factory()->officer()->create();
        $item = $this->createTestItem();

        $response = $this->actingAs($user)->get($this->editUrl($item));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('LootBiasTool/ItemEdit')
            ->has('item.data')
        );
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

        $response = $this->actingAs($user)->get($this->editUrl($item));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('LootBiasTool/ItemEdit')
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

        $response = $this->from(route('loot.items.edit', ['item' => $item, 'name' => 'test-item-'.$item->id]))->actingAs($user)->put(route('loot.items.priorities.update', $item), [
            'priorities' => [
                ['priority_id' => $priority->id, 'weight' => 0],
            ],
        ]);

        $response->assertRedirect(route('loot.items.edit', ['item' => $item, 'name' => 'test-item-'.$item->id]));
    }

    public function test_update_priorities_syncs_correctly(): void
    {
        $user = User::factory()->officer()->create();
        $item = $this->createTestItem();
        $priority1 = Priority::factory()->create();
        $priority2 = Priority::factory()->create();
        $priority3 = Priority::factory()->create();

        $item->priorities()->attach($priority1->id, ['weight' => 0]);

        $response = $this->from(route('loot.items.edit', ['item' => $item, 'name' => 'test-item-'.$item->id]))->actingAs($user)->put(route('loot.items.priorities.update', $item), [
            'priorities' => [
                ['priority_id' => $priority2->id, 'weight' => 0],
                ['priority_id' => $priority3->id, 'weight' => 1],
            ],
        ]);

        $response->assertRedirect(route('loot.items.edit', ['item' => $item, 'name' => 'test-item-'.$item->id]));

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

        $response = $this->from(route('loot.items.edit', ['item' => $item, 'name' => 'test-item-'.$item->id]))->actingAs($user)->put(route('loot.items.priorities.update', $item), [
            'priorities' => [],
        ]);

        $response->assertRedirect(route('loot.items.edit', ['item' => $item, 'name' => 'test-item-'.$item->id]));
        $item->refresh();
        $this->assertCount(0, $item->priorities);
    }

    public function test_update_priorities_handles_same_weight_priorities(): void
    {
        $user = User::factory()->officer()->create();
        $item = $this->createTestItem();
        $priority1 = Priority::factory()->create();
        $priority2 = Priority::factory()->create();

        $response = $this->from(route('loot.items.edit', ['item' => $item, 'name' => 'test-item-'.$item->id]))->actingAs($user)->put(route('loot.items.priorities.update', $item), [
            'priorities' => [
                ['priority_id' => $priority1->id, 'weight' => 0],
                ['priority_id' => $priority2->id, 'weight' => 0],
            ],
        ]);

        $response->assertRedirect(route('loot.items.edit', ['item' => $item, 'name' => 'test-item-'.$item->id]));

        $item->refresh();
        $this->assertCount(2, $item->priorities);

        $weights = $item->priorities->pluck('pivot.weight')->toArray();
        $this->assertEquals([0, 0], $weights);
    }

    public function test_update_notes_requires_authentication(): void
    {
        $item = $this->createTestItem();

        $response = $this->post(route('loot.items.notes.store', $item), [
            'notes' => 'Test notes',
        ]);

        $response->assertRedirect('/login');
    }

    public function test_update_notes_forbids_guest_users(): void
    {
        $user = User::factory()->guest()->create();
        $item = $this->createTestItem();

        $response = $this->actingAs($user)->post(route('loot.items.notes.store', $item), [
            'notes' => 'Test notes',
        ]);

        $response->assertForbidden();
    }

    public function test_update_notes_forbids_member_users(): void
    {
        $user = User::factory()->member()->create();
        $item = $this->createTestItem();

        $response = $this->actingAs($user)->post(route('loot.items.notes.store', $item), [
            'notes' => 'Test notes',
        ]);

        $response->assertForbidden();
    }

    public function test_update_notes_forbids_raider_users(): void
    {
        $user = User::factory()->raider()->create();
        $item = $this->createTestItem();

        $response = $this->actingAs($user)->post(route('loot.items.notes.store', $item), [
            'notes' => 'Test notes',
        ]);

        $response->assertForbidden();
    }

    public function test_update_notes_allows_officer_users(): void
    {
        $user = User::factory()->officer()->create();
        $item = $this->createTestItem();

        $response = $this->actingAs($user)->post(route('loot.items.notes.store', $item), [
            'notes' => 'Test notes content',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('lootcouncil_items', [
            'id' => $item->id,
            'notes' => 'Test notes content',
        ]);
    }

    public function test_update_notes_saves_notes_to_database(): void
    {
        $user = User::factory()->officer()->create();
        $item = $this->createTestItem();

        $this->actingAs($user)->post(route('loot.items.notes.store', $item), [
            'notes' => 'These are detailed officer notes about the item.',
        ]);

        $item->refresh();
        $this->assertEquals('These are detailed officer notes about the item.', $item->notes);
    }

    public function test_update_notes_allows_null_to_clear_notes(): void
    {
        $user = User::factory()->officer()->create();
        $item = $this->createTestItem();
        $item->update(['notes' => 'Existing notes']);

        $response = $this->actingAs($user)->post(route('loot.items.notes.store', $item), [
            'notes' => null,
        ]);

        $response->assertRedirect();
        $item->refresh();
        $this->assertNull($item->notes);
    }

    public function test_update_notes_allows_empty_string_to_clear_notes(): void
    {
        $user = User::factory()->officer()->create();
        $item = $this->createTestItem();
        $item->update(['notes' => 'Existing notes']);

        $response = $this->actingAs($user)->post(route('loot.items.notes.store', $item), [
            'notes' => '',
        ]);

        $response->assertRedirect();
        $item->refresh();
        $this->assertEquals('', $item->notes);
    }

    public function test_update_notes_validates_max_length(): void
    {
        $user = User::factory()->officer()->create();
        $item = $this->createTestItem();

        $response = $this->actingAs($user)->post(route('loot.items.notes.store', $item), [
            'notes' => str_repeat('a', 5001),
        ]);

        $response->assertSessionHasErrors(['notes']);
    }

    public function test_update_notes_allows_max_length(): void
    {
        $user = User::factory()->officer()->create();
        $item = $this->createTestItem();

        $response = $this->actingAs($user)->post(route('loot.items.notes.store', $item), [
            'notes' => str_repeat('a', 5000),
        ]);

        $response->assertRedirect();
        $response->assertSessionDoesntHaveErrors();
        $item->refresh();
        $this->assertEquals(5000, strlen($item->notes));
    }

    public function test_update_notes_validates_notes_is_string(): void
    {
        $user = User::factory()->officer()->create();
        $item = $this->createTestItem();

        $response = $this->actingAs($user)->post(route('loot.items.notes.store', $item), [
            'notes' => ['array', 'of', 'values'],
        ]);

        $response->assertSessionHasErrors(['notes']);
    }

    public function test_update_notes_overwrites_existing_notes(): void
    {
        $user = User::factory()->officer()->create();
        $item = $this->createTestItem();
        $item->update(['notes' => 'Original notes']);

        $this->actingAs($user)->post(route('loot.items.notes.store', $item), [
            'notes' => 'Updated notes',
        ]);

        $item->refresh();
        $this->assertEquals('Updated notes', $item->notes);
    }
}
