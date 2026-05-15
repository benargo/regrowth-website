<?php

namespace Tests\Feature\Api;

use App\Models\Boss;
use App\Models\Character;
use App\Models\DiscordRole;
use App\Models\Event;
use App\Models\EventAssignment;
use App\Models\EventAssignmentGroup;
use App\Models\Permission;
use App\Models\Raid;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class EventAssignmentControllerTest extends TestCase
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
        $memberRole->givePermissionTo(Permission::firstOrCreate(['name' => 'view-raid-plans', 'guard_name' => 'web']));
        $memberRole->givePermissionTo(Permission::firstOrCreate(['name' => 'manage-raid-plans', 'guard_name' => 'web']));

        $this->editor = User::factory()->create();
        $this->editor->discordRoles()->attach($memberRole->id);

        $this->viewer = User::factory()->create();

        $this->event = Event::factory()->create();
    }

    // ─── store() ──────────────────────────────────────────────────────────────

    #[Test]
    public function it_creates_an_assignment_and_returns_201(): void
    {
        $response = $this->actingAs($this->editor)
            ->postJson(route('api.events.assignments.store', $this->event));

        $response->assertCreated();
        $response->assertJsonStructure(['id', 'sort_order', 'boss_id', 'group_id', 'left_type', 'left_value', 'right_type', 'right_value']);

        $this->assertDatabaseHas('event_assignments', [
            'event_id' => $this->event->id,
            'left_value' => null,
            'right_value' => null,
        ]);
    }

    #[Test]
    public function it_creates_an_assignment_scoped_to_a_boss(): void
    {
        $raid = Raid::factory()->create();
        $boss = Boss::factory()->for($raid)->create();

        $response = $this->actingAs($this->editor)
            ->postJson(route('api.events.assignments.store', $this->event), [
                'boss_id' => $boss->id,
            ]);

        $response->assertCreated();
        $response->assertJsonPath('boss_id', $boss->id);
    }

    #[Test]
    public function it_creates_an_assignment_scoped_to_a_group(): void
    {
        $group = EventAssignmentGroup::factory()->for($this->event)->create();

        $response = $this->actingAs($this->editor)
            ->postJson(route('api.events.assignments.store', $this->event), [
                'group_id' => $group->id,
            ]);

        $response->assertCreated();
        $response->assertJsonPath('group_id', $group->id);
    }

    #[Test]
    public function it_returns_422_when_group_belongs_to_different_event_on_store(): void
    {
        $otherEvent = Event::factory()->create();
        $group = EventAssignmentGroup::factory()->for($otherEvent)->create();

        $this->actingAs($this->editor)
            ->postJson(route('api.events.assignments.store', $this->event), [
                'group_id' => $group->id,
            ])
            ->assertUnprocessable();
    }

    #[Test]
    public function it_returns_422_when_boss_id_does_not_exist_on_store(): void
    {
        $this->actingAs($this->editor)
            ->postJson(route('api.events.assignments.store', $this->event), ['boss_id' => 99999])
            ->assertUnprocessable();
    }

    #[Test]
    public function it_returns_403_on_store_when_user_cannot_update_event(): void
    {
        $this->actingAs($this->viewer)
            ->postJson(route('api.events.assignments.store', $this->event))
            ->assertForbidden();
    }

    #[Test]
    public function it_returns_401_on_store_when_unauthenticated(): void
    {
        $this->postJson(route('api.events.assignments.store', $this->event))->assertUnauthorized();
    }

    // ─── update() ─────────────────────────────────────────────────────────────

    #[Test]
    public function it_updates_left_and_right_fields(): void
    {
        $assignment = EventAssignment::factory()->for($this->event)->create([
            'left_value' => 'old left',
            'right_value' => 'old right',
        ]);

        $this->actingAs($this->editor)
            ->patchJson(route('api.events.assignments.update', [$this->event, $assignment]), [
                'left_value' => 'new left',
                'right_value' => 'new right',
            ])
            ->assertNoContent();

        $this->assertDatabaseHas('event_assignments', [
            'id' => $assignment->id,
            'left_value' => 'new left',
            'right_value' => 'new right',
        ]);
    }

    #[Test]
    public function it_moves_assignment_to_a_new_group(): void
    {
        $groupA = EventAssignmentGroup::factory()->for($this->event)->create();
        $groupB = EventAssignmentGroup::factory()->for($this->event)->create();
        $assignment = EventAssignment::factory()->for($this->event)->for($groupA, 'group')->create();

        $this->actingAs($this->editor)
            ->patchJson(route('api.events.assignments.update', [$this->event, $assignment]), ['group_id' => $groupB->id])
            ->assertNoContent();

        $this->assertDatabaseHas('event_assignments', ['id' => $assignment->id, 'group_id' => $groupB->id]);
    }

    #[Test]
    public function it_moves_assignment_to_a_different_boss(): void
    {
        $raid = Raid::factory()->create();
        $bossA = Boss::factory()->for($raid)->create();
        $bossB = Boss::factory()->for($raid)->create();
        $assignment = EventAssignment::factory()->for($this->event)->create(['boss_id' => $bossA->id]);

        $this->actingAs($this->editor)
            ->patchJson(route('api.events.assignments.update', [$this->event, $assignment]), ['boss_id' => $bossB->id])
            ->assertNoContent();

        $this->assertDatabaseHas('event_assignments', ['id' => $assignment->id, 'boss_id' => $bossB->id]);
    }

    #[Test]
    public function it_moves_assignment_to_general_by_nulling_boss_id(): void
    {
        $raid = Raid::factory()->create();
        $boss = Boss::factory()->for($raid)->create();
        $assignment = EventAssignment::factory()->for($this->event)->create(['boss_id' => $boss->id]);

        $this->actingAs($this->editor)
            ->patchJson(route('api.events.assignments.update', [$this->event, $assignment]), ['boss_id' => null])
            ->assertNoContent();

        $this->assertDatabaseHas('event_assignments', ['id' => $assignment->id, 'boss_id' => null]);
    }

    #[Test]
    public function it_returns_422_on_update_when_group_belongs_to_different_event(): void
    {
        $otherEvent = Event::factory()->create();
        $foreignGroup = EventAssignmentGroup::factory()->for($otherEvent)->create();
        $assignment = EventAssignment::factory()->for($this->event)->create();

        $this->actingAs($this->editor)
            ->patchJson(route('api.events.assignments.update', [$this->event, $assignment]), ['group_id' => $foreignGroup->id])
            ->assertUnprocessable();
    }

    #[Test]
    public function it_returns_404_on_update_when_assignment_belongs_to_different_event(): void
    {
        $otherEvent = Event::factory()->create();
        $assignment = EventAssignment::factory()->for($otherEvent)->create();

        $this->actingAs($this->editor)
            ->patchJson(route('api.events.assignments.update', [$this->event, $assignment]), ['left_value' => 'X'])
            ->assertNotFound();
    }

    #[Test]
    public function it_returns_403_on_update_when_user_cannot_update_event(): void
    {
        $assignment = EventAssignment::factory()->for($this->event)->create();

        $this->actingAs($this->viewer)
            ->patchJson(route('api.events.assignments.update', [$this->event, $assignment]), ['left_value' => 'X'])
            ->assertForbidden();
    }

    // ─── reorder() ────────────────────────────────────────────────────────────

    #[Test]
    public function it_reorders_assignments_by_array_position(): void
    {
        [$a, $b, $c] = EventAssignment::factory()->count(3)->for($this->event)->create()->all();

        $this->actingAs($this->editor)
            ->patchJson(route('api.events.assignments.reorder', $this->event), ['order' => [$c->id, $a->id, $b->id]])
            ->assertNoContent();

        $this->assertDatabaseHas('event_assignments', ['id' => $c->id, 'sort_order' => 0]);
        $this->assertDatabaseHas('event_assignments', ['id' => $a->id, 'sort_order' => 1]);
        $this->assertDatabaseHas('event_assignments', ['id' => $b->id, 'sort_order' => 2]);
    }

    #[Test]
    public function it_returns_422_on_reorder_when_ids_belong_to_different_event(): void
    {
        $otherEvent = Event::factory()->create();
        $foreign = EventAssignment::factory()->for($otherEvent)->create();
        $own = EventAssignment::factory()->for($this->event)->create();

        $this->actingAs($this->editor)
            ->patchJson(route('api.events.assignments.reorder', $this->event), ['order' => [$own->id, $foreign->id]])
            ->assertUnprocessable();
    }

    #[Test]
    public function it_returns_403_on_reorder_when_user_cannot_update_event(): void
    {
        $assignment = EventAssignment::factory()->for($this->event)->create();

        $this->actingAs($this->viewer)
            ->patchJson(route('api.events.assignments.reorder', $this->event), ['order' => [$assignment->id]])
            ->assertForbidden();
    }

    #[Test]
    public function it_updates_left_type_using_short_type_string(): void
    {
        $assignment = EventAssignment::factory()->for($this->event)->create();

        $this->actingAs($this->editor)
            ->patchJson(route('api.events.assignments.update', [$this->event, $assignment]), [
                'left_type' => 'character',
                'left_value' => '123',
            ])
            ->assertNoContent();

        $this->assertDatabaseHas('event_assignments', [
            'id' => $assignment->id,
            'left_type' => Character::class,
        ]);
    }

    #[Test]
    public function it_returns_422_for_unknown_type_string_on_update(): void
    {
        $assignment = EventAssignment::factory()->for($this->event)->create();

        $this->actingAs($this->editor)
            ->patchJson(route('api.events.assignments.update', [$this->event, $assignment]), [
                'left_type' => 'unknown_thing',
                'left_value' => 'anything',
            ])
            ->assertUnprocessable();
    }

    // ─── destroy() ────────────────────────────────────────────────────────────

    #[Test]
    public function it_deletes_an_assignment(): void
    {
        $assignment = EventAssignment::factory()->for($this->event)->create();

        $this->actingAs($this->editor)
            ->deleteJson(route('api.events.assignments.destroy', [$this->event, $assignment]))
            ->assertNoContent();

        $this->assertDatabaseMissing('event_assignments', ['id' => $assignment->id]);
    }

    #[Test]
    public function it_returns_404_on_destroy_when_assignment_belongs_to_different_event(): void
    {
        $otherEvent = Event::factory()->create();
        $assignment = EventAssignment::factory()->for($otherEvent)->create();

        $this->actingAs($this->editor)
            ->deleteJson(route('api.events.assignments.destroy', [$this->event, $assignment]))
            ->assertNotFound();
    }

    #[Test]
    public function it_returns_403_on_destroy_when_user_cannot_update_event(): void
    {
        $assignment = EventAssignment::factory()->for($this->event)->create();

        $this->actingAs($this->viewer)
            ->deleteJson(route('api.events.assignments.destroy', [$this->event, $assignment]))
            ->assertForbidden();
    }

    #[Test]
    public function test_update_only_left_side_does_not_overwrite_right_side(): void
    {
        $assignment = EventAssignment::factory()->for($this->event)->create([
            'left_type' => null,
            'left_value' => null,
            'right_type' => 'App\\Models\\TargetMarker',
            'right_value' => 'cross',
        ]);

        $this->actingAs($this->editor)
            ->patchJson(route('api.events.assignments.update', [$this->event, $assignment]), [
                'left_type' => 'character',
                'left_value' => '42',
            ])
            ->assertNoContent();

        $assignment->refresh();
        $this->assertSame('App\\Models\\TargetMarker', $assignment->right_type);
        $this->assertSame('cross', $assignment->right_value);
    }

    #[Test]
    public function test_update_only_right_side_does_not_overwrite_left_side(): void
    {
        $assignment = EventAssignment::factory()->for($this->event)->create([
            'left_type' => 'App\\Models\\Character',
            'left_value' => '42',
            'right_type' => null,
            'right_value' => null,
        ]);

        $this->actingAs($this->editor)
            ->patchJson(route('api.events.assignments.update', [$this->event, $assignment]), [
                'right_type' => 'target_marker',
                'right_value' => 'skull',
            ])
            ->assertNoContent();

        $assignment->refresh();
        $this->assertSame('App\\Models\\Character', $assignment->left_type);
        $this->assertSame('42', $assignment->left_value);
    }
}
