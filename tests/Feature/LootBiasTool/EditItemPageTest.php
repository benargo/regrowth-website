<?php

namespace Tests\Feature\LootBiasTool;

use App\Models\Boss;
use App\Models\DiscordRole;
use App\Models\Item;
use App\Models\LootPriority;
use App\Models\Permission;
use App\Models\Phase;
use App\Models\Raid;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Blizzard\MocksBlizzardServices;
use Tests\TestCase;

#[Group('loot')]
class EditItemPageTest extends TestCase
{
    use MocksBlizzardServices;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpPermissions();
        $this->mockItemService();
    }

    #[Test]
    public function edit_item_requires_authentication(): void
    {
        $item = $this->createTestItem();

        $response = $this->get($this->editUrl($item));

        $response->assertRedirect('/login');
    }

    #[Group('authorization')]
    #[Test]
    public function edit_item_forbids_guest_users(): void
    {
        $user = User::factory()->guest()->create();
        $item = $this->createTestItem();

        $response = $this->actingAs($user)->get($this->editUrl($item));

        $response->assertForbidden();
    }

    #[Group('authorization')]
    #[Test]
    public function edit_item_forbids_member_users(): void
    {
        $user = User::factory()->member()->create();
        $item = $this->createTestItem();

        $response = $this->actingAs($user)->get($this->editUrl($item));

        $response->assertForbidden();
    }

    #[Group('authorization')]
    #[Test]
    public function edit_item_forbids_raider_users(): void
    {
        $user = User::factory()->raider()->create();
        $item = $this->createTestItem();

        $response = $this->actingAs($user)->get($this->editUrl($item));

        $response->assertForbidden();
    }

    #[Test]
    public function edit_item_allows_officer_users(): void
    {
        $user = User::factory()->officer()->create();
        $item = $this->createTestItem();

        $response = $this->actingAs($user)->get($this->editUrl($item));

        $response->assertOk();
    }

    #[Test]
    public function edit_item_redirects_from_incorrect_slug_to_correct_slug(): void
    {
        $user = User::factory()->officer()->create();
        $item = $this->createTestItem();

        $response = $this->actingAs($user)->get($this->editUrl($item, 'wrong-slug'));

        $response->assertRedirect(route('loot.items.edit', ['item' => $item->id, 'slug' => 'test-item-'.$item->id]));
        $response->assertStatus(303);
    }

    #[Test]
    public function edit_item_renders_with_correct_slug(): void
    {
        $user = User::factory()->officer()->create();
        $item = $this->createTestItem();

        $response = $this->actingAs($user)->get($this->editUrl($item));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Loot/Items/Edit')
            ->has('item.data')
        );
    }

    #[Test]
    public function edit_item_returns_item_and_all_priorities(): void
    {
        $user = User::factory()->officer()->create();
        $item = $this->createTestItem();
        $priority1 = LootPriority::factory()->role()->create(['title' => 'Tank']);
        $priority2 = LootPriority::factory()->role()->create(['title' => 'Healer']);
        $priority3 = LootPriority::factory()->role()->create(['title' => 'DPS']);

        $item->priorities()->attach($priority1->id, ['weight' => 0]);
        $item->priorities()->attach($priority2->id, ['weight' => 1]);

        $response = $this->actingAs($user)->get($this->editUrl($item));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Loot/Items/Edit')
            ->has('item.data')
            ->has('item.data.priorities', 2)
            ->has('allPriorities.data', 3)
        );
    }

    #[Test]
    public function update_priorities_requires_authentication(): void
    {
        $item = $this->createTestItem();

        $response = $this->put(route('loot.items.priorities.update', $item), [
            'priorities' => [],
        ]);

        $response->assertRedirect('/login');
    }

    #[Group('authorization')]
    #[Test]
    public function update_priorities_forbids_non_officers(): void
    {
        $user = User::factory()->member()->create();
        $item = $this->createTestItem();

        $response = $this->actingAs($user)->put(route('loot.items.priorities.update', $item), [
            'priorities' => [],
        ]);

        $response->assertForbidden();
    }

    #[Test]
    public function update_priorities_allows_officers(): void
    {
        $user = User::factory()->officer()->create();
        $item = $this->createTestItem();
        $priority = LootPriority::factory()->create();

        $response = $this->from(route('loot.items.edit', ['item' => $item, 'slug' => 'test-item-'.$item->id]))->actingAs($user)->put(route('loot.items.priorities.update', $item), [
            'priorities' => [
                ['priority_id' => $priority->id, 'weight' => 0],
            ],
        ]);

        $response->assertRedirect(route('loot.items.edit', ['item' => $item, 'slug' => 'test-item-'.$item->id]));
    }

    #[Test]
    public function update_priorities_syncs_correctly(): void
    {
        $user = User::factory()->officer()->create();
        $item = $this->createTestItem();
        $priority1 = LootPriority::factory()->create();
        $priority2 = LootPriority::factory()->create();
        $priority3 = LootPriority::factory()->create();

        $item->priorities()->attach($priority1->id, ['weight' => 0]);

        $response = $this->from(route('loot.items.edit', ['item' => $item, 'slug' => 'test-item-'.$item->id]))->actingAs($user)->put(route('loot.items.priorities.update', $item), [
            'priorities' => [
                ['priority_id' => $priority2->id, 'weight' => 0],
                ['priority_id' => $priority3->id, 'weight' => 1],
            ],
        ]);

        $response->assertRedirect(route('loot.items.edit', ['item' => $item, 'slug' => 'test-item-'.$item->id]));

        $item->refresh();
        $this->assertCount(2, $item->priorities);
        $this->assertTrue($item->priorities->contains('id', $priority2->id));
        $this->assertTrue($item->priorities->contains('id', $priority3->id));
        $this->assertFalse($item->priorities->contains('id', $priority1->id));
    }

    #[Group('validation')]
    #[Test]
    public function update_priorities_validates_priority_ids(): void
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

    #[Group('validation')]
    #[Test]
    public function update_priorities_validates_weights(): void
    {
        $user = User::factory()->officer()->create();
        $item = $this->createTestItem();
        $priority = LootPriority::factory()->create();

        $response = $this->actingAs($user)->put(route('loot.items.priorities.update', $item), [
            'priorities' => [
                ['priority_id' => $priority->id, 'weight' => -1],
            ],
        ]);

        $response->assertSessionHasErrors(['priorities.0.weight']);
    }

    #[Test]
    public function update_priorities_handles_empty_array(): void
    {
        $user = User::factory()->officer()->create();
        $item = $this->createTestItem();
        $priority = LootPriority::factory()->create();

        $item->priorities()->attach($priority->id, ['weight' => 0]);

        $response = $this->from(route('loot.items.edit', ['item' => $item, 'slug' => 'test-item-'.$item->id]))->actingAs($user)->put(route('loot.items.priorities.update', $item), [
            'priorities' => [],
        ]);

        $response->assertRedirect(route('loot.items.edit', ['item' => $item, 'slug' => 'test-item-'.$item->id]));
        $item->refresh();
        $this->assertCount(0, $item->priorities);
    }

    #[Test]
    public function update_priorities_handles_same_weight_priorities(): void
    {
        $user = User::factory()->officer()->create();
        $item = $this->createTestItem();
        $priority1 = LootPriority::factory()->create();
        $priority2 = LootPriority::factory()->create();

        $response = $this->from(route('loot.items.edit', ['item' => $item, 'slug' => 'test-item-'.$item->id]))->actingAs($user)->put(route('loot.items.priorities.update', $item), [
            'priorities' => [
                ['priority_id' => $priority1->id, 'weight' => 0],
                ['priority_id' => $priority2->id, 'weight' => 0],
            ],
        ]);

        $response->assertRedirect(route('loot.items.edit', ['item' => $item, 'slug' => 'test-item-'.$item->id]));

        $item->refresh();
        $this->assertCount(2, $item->priorities);

        $weights = $item->priorities->pluck('pivot.weight')->toArray();
        $this->assertEquals([0, 0], $weights);
    }

    #[Test]
    public function update_notes_requires_authentication(): void
    {
        $item = $this->createTestItem();

        $response = $this->post(route('loot.items.notes.store', $item), [
            'notes' => 'Test notes',
        ]);

        $response->assertRedirect('/login');
    }

    #[Group('authorization')]
    #[Test]
    public function update_notes_forbids_guest_users(): void
    {
        $user = User::factory()->guest()->create();
        $item = $this->createTestItem();

        $response = $this->actingAs($user)->post(route('loot.items.notes.store', $item), [
            'notes' => 'Test notes',
        ]);

        $response->assertForbidden();
    }

    #[Group('authorization')]
    #[Test]
    public function update_notes_forbids_member_users(): void
    {
        $user = User::factory()->member()->create();
        $item = $this->createTestItem();

        $response = $this->actingAs($user)->post(route('loot.items.notes.store', $item), [
            'notes' => 'Test notes',
        ]);

        $response->assertForbidden();
    }

    #[Group('authorization')]
    #[Test]
    public function update_notes_forbids_raider_users(): void
    {
        $user = User::factory()->raider()->create();
        $item = $this->createTestItem();

        $response = $this->actingAs($user)->post(route('loot.items.notes.store', $item), [
            'notes' => 'Test notes',
        ]);

        $response->assertForbidden();
    }

    #[Test]
    public function update_notes_allows_officer_users(): void
    {
        $user = User::factory()->officer()->create();
        $item = $this->createTestItem();

        $response = $this->actingAs($user)->post(route('loot.items.notes.store', $item), [
            'notes' => 'Test notes content',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('items', [
            'id' => $item->id,
            'notes' => 'Test notes content',
        ]);
    }

    #[Test]
    public function update_notes_saves_notes_to_database(): void
    {
        $user = User::factory()->officer()->create();
        $item = $this->createTestItem();

        $this->actingAs($user)->post(route('loot.items.notes.store', $item), [
            'notes' => 'These are detailed officer notes about the item.',
        ]);

        $item->refresh();
        $this->assertEquals('These are detailed officer notes about the item.', $item->notes);
    }

    #[Test]
    public function update_notes_allows_null_to_clear_notes(): void
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

    #[Test]
    public function update_notes_allows_empty_string_to_clear_notes(): void
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

    #[Group('validation')]
    #[Test]
    public function update_notes_validates_max_length(): void
    {
        $user = User::factory()->officer()->create();
        $item = $this->createTestItem();

        $response = $this->actingAs($user)->post(route('loot.items.notes.store', $item), [
            'notes' => str_repeat('a', 5001),
        ]);

        $response->assertSessionHasErrors(['notes']);
    }

    #[Test]
    public function update_notes_allows_max_length(): void
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

    #[Group('validation')]
    #[Test]
    public function update_notes_validates_notes_is_string(): void
    {
        $user = User::factory()->officer()->create();
        $item = $this->createTestItem();

        $response = $this->actingAs($user)->post(route('loot.items.notes.store', $item), [
            'notes' => ['array', 'of', 'values'],
        ]);

        $response->assertSessionHasErrors(['notes']);
    }

    #[Test]
    public function update_notes_overwrites_existing_notes(): void
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

    protected function setUpPermissions(): void
    {
        $editItems = Permission::firstOrCreate(['name' => 'edit-items', 'guard_name' => 'web']);

        $officerRole = DiscordRole::firstOrCreate(['id' => '829021769448816691'], ['name' => 'Officer', 'position' => 5, 'is_visible' => true]);
        $officerRole->givePermissionTo($editItems);
    }

    protected function createTestItem(): Item
    {
        $phase = Phase::factory()->started()->create();
        $raid = Raid::factory()->create(['phase_id' => $phase->id]);
        $boss = Boss::factory()->create(['raid_id' => $raid->id]);

        $item = Item::factory()->create(['raid_id' => $raid->id, 'boss_id' => $boss->id]);
        $item->update(['name' => "Test Item {$item->id}"]);

        return $item->fresh();
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
}
