<?php

namespace Tests\Feature\Api;

use App\Models\DiscordRole;
use App\Models\Event;
use App\Models\EventAssignment;
use App\Models\EventAssignmentGroup;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class EventGroupControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $editor;

    protected User $viewer;

    protected Event $event;

    protected function setUp(): void
    {
        parent::setUp();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $memberRole = DiscordRole::create([
            'id' => '829022020301094922',
            'name' => 'Member',
            'position' => 1,
            'is_visible' => true,
        ]);
        $memberRole->givePermissionTo(Permission::firstOrCreate(['name' => 'manage-raid-plans', 'guard_name' => 'web']));

        $this->editor = User::factory()->create();
        $this->editor->discordRoles()->attach($memberRole->id);

        $this->viewer = User::factory()->create();

        $this->event = Event::factory()->create();
    }

    // ─── store() ──────────────────────────────────────────────────────────────

    #[Test]
    public function it_creates_a_group_and_returns_201(): void
    {
        $response = $this->actingAs($this->editor)
            ->postJson(route('api.events.groups.store', $this->event), ['name' => 'Healers']);

        $response->assertCreated();
        $response->assertJsonStructure(['id', 'name', 'sort_order']);
        $response->assertJsonPath('name', 'Healers');

        $this->assertDatabaseHas('event_assignment_groups', [
            'event_id' => $this->event->id,
            'name' => 'Healers',
        ]);
    }

    #[Test]
    public function it_defaults_name_to_new_group_when_none_provided(): void
    {
        $response = $this->actingAs($this->editor)
            ->postJson(route('api.events.groups.store', $this->event), []);

        $response->assertCreated();
        $response->assertJsonPath('name', 'New group');

        $this->assertDatabaseHas('event_assignment_groups', [
            'event_id' => $this->event->id,
            'name' => 'New group',
        ]);
    }

    #[Test]
    public function it_auto_increments_sort_order_based_on_existing_groups(): void
    {
        EventAssignmentGroup::factory()->for($this->event)->create(['sort_order' => 3]);

        $response = $this->actingAs($this->editor)
            ->postJson(route('api.events.groups.store', $this->event), ['name' => 'Second Group']);

        $response->assertCreated();
        $response->assertJsonPath('sort_order', 4);
    }

    #[Test]
    public function it_returns_403_on_store_when_user_cannot_update_event(): void
    {
        $response = $this->actingAs($this->viewer)
            ->postJson(route('api.events.groups.store', $this->event), ['name' => 'Tanks']);

        $response->assertForbidden();
    }

    #[Test]
    public function it_returns_401_on_store_when_unauthenticated(): void
    {
        $this->postJson(route('api.events.groups.store', $this->event))->assertUnauthorized();
    }

    // ─── update() ─────────────────────────────────────────────────────────────

    #[Test]
    public function it_updates_group_name(): void
    {
        $group = EventAssignmentGroup::factory()->for($this->event)->create(['name' => 'Old Name']);

        $response = $this->actingAs($this->editor)
            ->patchJson(route('api.events.groups.update', [$this->event, $group]), ['name' => 'New Name']);

        $response->assertNoContent();
        $this->assertDatabaseHas('event_assignment_groups', ['id' => $group->id, 'name' => 'New Name']);
    }

    #[Test]
    public function it_updates_group_sort_order(): void
    {
        $group = EventAssignmentGroup::factory()->for($this->event)->create(['sort_order' => 0]);

        $this->actingAs($this->editor)
            ->patchJson(route('api.events.groups.update', [$this->event, $group]), ['sort_order' => 5])
            ->assertNoContent();

        $this->assertDatabaseHas('event_assignment_groups', ['id' => $group->id, 'sort_order' => 5]);
    }

    #[Test]
    public function it_returns_404_on_update_when_group_belongs_to_different_event(): void
    {
        $otherEvent = Event::factory()->create();
        $group = EventAssignmentGroup::factory()->for($otherEvent)->create();

        $this->actingAs($this->editor)
            ->patchJson(route('api.events.groups.update', [$this->event, $group]), ['name' => 'Hack'])
            ->assertNotFound();
    }

    #[Test]
    public function it_returns_403_on_update_when_user_cannot_update_event(): void
    {
        $group = EventAssignmentGroup::factory()->for($this->event)->create();

        $this->actingAs($this->viewer)
            ->patchJson(route('api.events.groups.update', [$this->event, $group]), ['name' => 'X'])
            ->assertForbidden();
    }

    // ─── reorder() ────────────────────────────────────────────────────────────

    #[Test]
    public function it_reorders_groups_by_array_position(): void
    {
        [$a, $b, $c] = EventAssignmentGroup::factory()->count(3)->for($this->event)->create()->all();

        $this->actingAs($this->editor)
            ->patchJson(route('api.events.groups.reorder', $this->event), ['order' => [$c->id, $a->id, $b->id]])
            ->assertNoContent();

        $this->assertDatabaseHas('event_assignment_groups', ['id' => $c->id, 'sort_order' => 0]);
        $this->assertDatabaseHas('event_assignment_groups', ['id' => $a->id, 'sort_order' => 1]);
        $this->assertDatabaseHas('event_assignment_groups', ['id' => $b->id, 'sort_order' => 2]);
    }

    #[Test]
    public function it_returns_422_on_reorder_when_ids_belong_to_different_event(): void
    {
        $otherEvent = Event::factory()->create();
        $foreign = EventAssignmentGroup::factory()->for($otherEvent)->create();
        $own = EventAssignmentGroup::factory()->for($this->event)->create();

        $this->actingAs($this->editor)
            ->patchJson(route('api.events.groups.reorder', $this->event), ['order' => [$own->id, $foreign->id]])
            ->assertUnprocessable();
    }

    #[Test]
    public function it_returns_403_on_reorder_when_user_cannot_update_event(): void
    {
        $group = EventAssignmentGroup::factory()->for($this->event)->create();

        $this->actingAs($this->viewer)
            ->patchJson(route('api.events.groups.reorder', $this->event), ['order' => [$group->id]])
            ->assertForbidden();
    }

    // ─── destroy() ────────────────────────────────────────────────────────────

    #[Test]
    public function it_deletes_a_group(): void
    {
        $group = EventAssignmentGroup::factory()->for($this->event)->create();

        $this->actingAs($this->editor)
            ->deleteJson(route('api.events.groups.destroy', [$this->event, $group]))
            ->assertNoContent();

        $this->assertDatabaseMissing('event_assignment_groups', ['id' => $group->id]);
    }

    #[Test]
    public function it_cascades_deletes_assignments_when_group_is_deleted(): void
    {
        $group = EventAssignmentGroup::factory()->for($this->event)->create();
        $assignment = EventAssignment::factory()->for($this->event)->for($group, 'group')->create();

        $this->actingAs($this->editor)
            ->deleteJson(route('api.events.groups.destroy', [$this->event, $group]))
            ->assertNoContent();

        $this->assertDatabaseMissing('event_assignment_groups', ['id' => $group->id]);
        $this->assertDatabaseMissing('event_assignments', ['id' => $assignment->id]);
    }

    #[Test]
    public function it_returns_404_on_destroy_when_group_belongs_to_different_event(): void
    {
        $otherEvent = Event::factory()->create();
        $group = EventAssignmentGroup::factory()->for($otherEvent)->create();

        $this->actingAs($this->editor)
            ->deleteJson(route('api.events.groups.destroy', [$this->event, $group]))
            ->assertNotFound();
    }

    #[Test]
    public function it_returns_403_on_destroy_when_user_cannot_update_event(): void
    {
        $group = EventAssignmentGroup::factory()->for($this->event)->create();

        $this->actingAs($this->viewer)
            ->deleteJson(route('api.events.groups.destroy', [$this->event, $group]))
            ->assertForbidden();
    }

    #[Test]
    public function it_deletes_an_empty_group_with_no_assignments(): void
    {
        $group = EventAssignmentGroup::factory()->for($this->event)->create();

        $this->actingAs($this->editor)
            ->deleteJson(route('api.events.groups.destroy', [$this->event, $group]))
            ->assertNoContent();

        $this->assertDatabaseMissing('event_assignment_groups', ['id' => $group->id]);
    }

    #[Test]
    public function it_does_not_delete_assignments_in_other_groups_when_one_group_is_destroyed(): void
    {
        $groupA = EventAssignmentGroup::factory()->for($this->event)->create();
        $groupB = EventAssignmentGroup::factory()->for($this->event)->create();
        $assignmentInA = EventAssignment::factory()->for($this->event)->for($groupA, 'group')->create();
        $assignmentInB = EventAssignment::factory()->for($this->event)->for($groupB, 'group')->create();

        $this->actingAs($this->editor)
            ->deleteJson(route('api.events.groups.destroy', [$this->event, $groupA]))
            ->assertNoContent();

        $this->assertDatabaseMissing('event_assignment_groups', ['id' => $groupA->id]);
        $this->assertDatabaseMissing('event_assignments', ['id' => $assignmentInA->id]);
        $this->assertDatabaseHas('event_assignment_groups', ['id' => $groupB->id]);
        $this->assertDatabaseHas('event_assignments', ['id' => $assignmentInB->id]);
    }
}
