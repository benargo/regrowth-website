<?php

namespace Tests\Feature\LootBiasTool;

use App\Events\Broadcasts\ItemUpdated;
use App\Models\Boss;
use App\Models\DiscordRole;
use App\Models\Item;
use App\Models\LootPriority;
use App\Models\Permission;
use App\Models\Phase;
use App\Models\Raid;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('loot')]
class UpdateItemTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpPermissions();
    }

    protected function setUpPermissions(): void
    {
        $editItems = Permission::firstOrCreate(['name' => 'edit-items', 'guard_name' => 'web']);

        $officerRole = DiscordRole::firstOrCreate(['id' => '829021769448816691'], ['name' => 'Officer', 'position' => 5, 'is_visible' => true]);
        $officerRole->givePermissionTo($editItems);
    }

    protected function createItem(): Item
    {
        $phase = Phase::factory()->started()->create();
        $raid = Raid::factory()->create(['phase_id' => $phase->id]);
        $boss = Boss::factory()->create(['raid_id' => $raid->id]);

        return Item::factory()->create(['raid_id' => $raid->id, 'boss_id' => $boss->id]);
    }

    protected function editUrl(Item $item): string
    {
        return route('loot.items.edit', ['item' => $item->id, 'slug' => $item->slug ?: "item-{$item->id}"]);
    }

    // ==========================================
    // Authentication and authorization
    // ==========================================

    #[Group('authorization')]
    #[Test]
    public function update_requires_authentication(): void
    {
        $item = $this->createItem();

        $this->patch(route('loot.items.update', $item), ['notes' => 'hello'])
            ->assertRedirect(route('login'));

        $this->assertDatabaseMissing('items', ['id' => $item->id, 'notes' => 'hello']);
    }

    #[Group('authorization')]
    #[Test]
    public function update_forbids_raider_users(): void
    {
        $user = User::factory()->raider()->create();
        $item = $this->createItem();

        $this->actingAs($user)
            ->patch(route('loot.items.update', $item), ['notes' => 'hello'])
            ->assertForbidden();
    }

    #[Group('authorization')]
    #[Test]
    public function update_allows_officer_users(): void
    {
        $user = User::factory()->officer()->create();
        $item = $this->createItem();

        $this->actingAs($user)
            ->from($this->editUrl($item))
            ->patch(route('loot.items.update', $item), ['notes' => 'Test notes'])
            ->assertRedirect($this->editUrl($item));
    }

    // ==========================================
    // Notes-only payload
    // ==========================================

    #[Test]
    public function update_saves_notes_when_notes_payload_sent(): void
    {
        $user = User::factory()->officer()->create();
        $item = $this->createItem();

        $this->actingAs($user)
            ->from($this->editUrl($item))
            ->patch(route('loot.items.update', $item), ['notes' => 'New officer notes']);

        $this->assertDatabaseHas('items', ['id' => $item->id, 'notes' => 'New officer notes']);
    }

    #[Test]
    public function update_clears_notes_when_null_sent(): void
    {
        $user = User::factory()->officer()->create();
        $item = $this->createItem();
        $item->update(['notes' => 'Existing notes']);

        $this->actingAs($user)
            ->from($this->editUrl($item))
            ->patch(route('loot.items.update', $item), ['notes' => null]);

        $item->refresh();
        $this->assertNull($item->notes);
    }

    #[Test]
    public function update_clears_notes_when_empty_string_sent(): void
    {
        $user = User::factory()->officer()->create();
        $item = $this->createItem();
        $item->update(['notes' => 'Existing notes']);

        $this->actingAs($user)
            ->from($this->editUrl($item))
            ->patch(route('loot.items.update', $item), ['notes' => '']);

        $item->refresh();
        $this->assertEquals('', $item->notes);
    }

    #[Group('validation')]
    #[Test]
    public function update_rejects_notes_exceeding_max_length(): void
    {
        $user = User::factory()->officer()->create();
        $item = $this->createItem();

        $this->actingAs($user)
            ->from($this->editUrl($item))
            ->patch(route('loot.items.update', $item), ['notes' => str_repeat('a', 5001)])
            ->assertSessionHasErrors('notes');
    }

    #[Test]
    public function update_accepts_notes_at_max_length(): void
    {
        $user = User::factory()->officer()->create();
        $item = $this->createItem();

        $this->actingAs($user)
            ->from($this->editUrl($item))
            ->patch(route('loot.items.update', $item), ['notes' => str_repeat('a', 5000)])
            ->assertSessionHasNoErrors();

        $item->refresh();
        $this->assertEquals(5000, strlen($item->notes));
    }

    #[Group('validation')]
    #[Test]
    public function update_rejects_non_string_notes(): void
    {
        $user = User::factory()->officer()->create();
        $item = $this->createItem();

        $this->actingAs($user)
            ->from($this->editUrl($item))
            ->patch(route('loot.items.update', $item), ['notes' => ['array', 'of', 'values']])
            ->assertSessionHasErrors('notes');
    }

    #[Test]
    public function update_overwrites_existing_notes(): void
    {
        $user = User::factory()->officer()->create();
        $item = $this->createItem();
        $item->update(['notes' => 'Original notes']);

        $this->actingAs($user)
            ->from($this->editUrl($item))
            ->patch(route('loot.items.update', $item), ['notes' => 'Updated notes']);

        $item->refresh();
        $this->assertEquals('Updated notes', $item->notes);
    }

    // ==========================================
    // Priorities-only payload
    // ==========================================

    #[Test]
    public function update_syncs_priorities_when_priorities_payload_sent(): void
    {
        $user = User::factory()->officer()->create();
        $item = $this->createItem();
        $priority1 = LootPriority::factory()->create();
        $priority2 = LootPriority::factory()->create();
        $priority3 = LootPriority::factory()->create();

        $item->priorities()->attach($priority1->id, ['weight' => 0]);

        $this->actingAs($user)
            ->from($this->editUrl($item))
            ->patch(route('loot.items.update', $item), [
                'priorities' => [
                    ['priority_id' => $priority2->id, 'weight' => 0],
                    ['priority_id' => $priority3->id, 'weight' => 1],
                ],
            ])
            ->assertSessionHasNoErrors();

        $item->refresh();
        $this->assertCount(2, $item->priorities);
        $this->assertTrue($item->priorities->contains('id', $priority2->id));
        $this->assertTrue($item->priorities->contains('id', $priority3->id));
        $this->assertFalse($item->priorities->contains('id', $priority1->id));
    }

    #[Group('validation')]
    #[Test]
    public function update_rejects_invalid_priority_id(): void
    {
        $user = User::factory()->officer()->create();
        $item = $this->createItem();

        $this->actingAs($user)
            ->from($this->editUrl($item))
            ->patch(route('loot.items.update', $item), [
                'priorities' => [
                    ['priority_id' => 99999, 'weight' => 0],
                ],
            ])
            ->assertSessionHasErrors('priorities.0.priority_id');
    }

    #[Group('validation')]
    #[Test]
    public function update_rejects_negative_weight(): void
    {
        $user = User::factory()->officer()->create();
        $item = $this->createItem();
        $priority = LootPriority::factory()->create();

        $this->actingAs($user)
            ->from($this->editUrl($item))
            ->patch(route('loot.items.update', $item), [
                'priorities' => [
                    ['priority_id' => $priority->id, 'weight' => -1],
                ],
            ])
            ->assertSessionHasErrors('priorities.0.weight');
    }

    #[Test]
    public function update_clears_priorities_when_empty_array_sent(): void
    {
        $user = User::factory()->officer()->create();
        $item = $this->createItem();
        $priority = LootPriority::factory()->create();
        $item->priorities()->attach($priority->id, ['weight' => 0]);

        $this->actingAs($user)
            ->from($this->editUrl($item))
            ->patch(route('loot.items.update', $item), ['priorities' => []])
            ->assertSessionHasNoErrors();

        $item->refresh();
        $this->assertCount(0, $item->priorities);
    }

    #[Test]
    public function update_handles_same_weight_priorities(): void
    {
        $user = User::factory()->officer()->create();
        $item = $this->createItem();
        $priority1 = LootPriority::factory()->create();
        $priority2 = LootPriority::factory()->create();

        $this->actingAs($user)
            ->from($this->editUrl($item))
            ->patch(route('loot.items.update', $item), [
                'priorities' => [
                    ['priority_id' => $priority1->id, 'weight' => 0],
                    ['priority_id' => $priority2->id, 'weight' => 0],
                ],
            ])
            ->assertSessionHasNoErrors();

        $item->refresh();
        $this->assertCount(2, $item->priorities);

        $weights = $item->priorities->pluck('pivot.weight')->toArray();
        $this->assertEquals([0, 0], $weights);
    }

    // ==========================================
    // Combined payload
    // ==========================================

    #[Test]
    public function update_saves_both_notes_and_priorities_in_single_request(): void
    {
        $user = User::factory()->officer()->create();
        $item = $this->createItem();
        $priority = LootPriority::factory()->create();

        $this->actingAs($user)
            ->from($this->editUrl($item))
            ->patch(route('loot.items.update', $item), [
                'notes' => 'Combined save notes',
                'priorities' => [
                    ['priority_id' => $priority->id, 'weight' => 0],
                ],
            ])
            ->assertSessionHasNoErrors();

        $item->refresh();
        $this->assertEquals('Combined save notes', $item->notes);
        $this->assertCount(1, $item->priorities);
        $this->assertTrue($item->priorities->contains('id', $priority->id));
    }

    // ==========================================
    // Independent field behaviour (sometimes)
    // ==========================================

    #[Test]
    public function update_notes_only_leaves_priorities_untouched(): void
    {
        $user = User::factory()->officer()->create();
        $item = $this->createItem();
        $priority = LootPriority::factory()->create();
        $item->priorities()->attach($priority->id, ['weight' => 0]);

        $this->actingAs($user)
            ->from($this->editUrl($item))
            ->patch(route('loot.items.update', $item), ['notes' => 'Notes only']);

        $item->refresh();
        $this->assertEquals('Notes only', $item->notes);
        $this->assertCount(1, $item->priorities);
        $this->assertTrue($item->priorities->contains('id', $priority->id));
    }

    #[Test]
    public function update_priorities_only_leaves_notes_untouched(): void
    {
        $user = User::factory()->officer()->create();
        $item = $this->createItem();
        $priority = LootPriority::factory()->create();
        $item->update(['notes' => 'Original notes']);

        $this->actingAs($user)
            ->from($this->editUrl($item))
            ->patch(route('loot.items.update', $item), [
                'priorities' => [
                    ['priority_id' => $priority->id, 'weight' => 0],
                ],
            ]);

        $item->refresh();
        $this->assertEquals('Original notes', $item->notes);
        $this->assertCount(1, $item->priorities);
    }

    // ==========================================
    // Trash items (no boss)
    // ==========================================

    #[Test]
    public function officers_can_update_a_trash_item_with_no_boss(): void
    {
        $item = Item::factory()->withRaid()->trashDrop()->withName('Trash Item')->create();

        $this->actingAs(User::factory()->officer()->create())
            ->from($this->editUrl($item))
            ->patch(route('loot.items.update', $item), ['notes' => 'Trash notes'])
            ->assertRedirect();

        $this->assertDatabaseHas('items', ['id' => $item->id, 'notes' => 'Trash notes']);
    }

    // ==========================================
    // Broadcasting
    // ==========================================

    #[Test]
    public function update_dispatches_item_updated_broadcast_on_success(): void
    {
        Event::fake([ItemUpdated::class]);

        $user = User::factory()->officer()->create();
        $item = $this->createItem();

        $this->actingAs($user)
            ->from($this->editUrl($item))
            ->patch(route('loot.items.update', $item), ['notes' => 'Broadcast test'])
            ->assertSessionHasNoErrors();

        Event::assertDispatched(ItemUpdated::class, function (ItemUpdated $event) use ($item) {
            return $event->item->id === $item->id;
        });
    }

    #[Test]
    public function update_does_not_broadcast_when_validation_fails(): void
    {
        Event::fake([ItemUpdated::class]);

        $user = User::factory()->officer()->create();
        $item = $this->createItem();

        $this->actingAs($user)
            ->from($this->editUrl($item))
            ->patch(route('loot.items.update', $item), ['notes' => str_repeat('a', 5001)])
            ->assertSessionHasErrors('notes');

        Event::assertNotDispatched(ItemUpdated::class);
    }
}
