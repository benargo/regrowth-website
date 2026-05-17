<?php

namespace Tests\Feature\Events\Broadcasts;

use App\Contracts\Events\FlushesRaidingCache;
use App\Events\Broadcasts\AssignmentChanged;
use App\Events\Broadcasts\CompositionChanged;
use App\Events\Broadcasts\GroupChanged;
use App\Models\Boss;
use App\Models\Event;
use App\Models\EventAssignment;
use App\Models\EventAssignmentGroup;
use App\Models\User;
use Illuminate\Broadcasting\BroadcastEvent;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Database\Eloquent\BroadcastableModelEventOccurred;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BroadcastEventsTest extends TestCase
{
    use RefreshDatabase;

    // ─── EventAssignment model broadcasts ────────────────────────────────────

    #[Test]
    public function event_assignment_created_broadcasts_on_private_event_channel(): void
    {
        $event = Event::factory()->create();
        $assignment = EventAssignment::factory()->for($event)->make();

        $channels = $assignment->broadcastOn('created');

        $this->assertCount(1, $channels);
        $this->assertInstanceOf(PrivateChannel::class, $channels[0]);
        $this->assertEquals("private-event.{$event->id}", $channels[0]->name);
    }

    #[Test]
    public function event_assignment_broadcasts_as_lifecycle_specific_names(): void
    {
        $event = Event::factory()->create();
        $assignment = EventAssignment::factory()->for($event)->make();

        $this->assertEquals('EventAssignmentCreated', $assignment->broadcastAs('created'));
        $this->assertEquals('EventAssignmentUpdated', $assignment->broadcastAs('updated'));
        $this->assertEquals('EventAssignmentDeleted', $assignment->broadcastAs('deleted'));
    }

    #[Test]
    public function event_assignment_broadcast_with_includes_assignment_payload(): void
    {
        $event = Event::factory()->create();
        $assignment = EventAssignment::factory()->for($event)->create();

        $payload = $assignment->broadcastWith('updated');

        $this->assertArrayHasKey('assignment', $payload);
        $this->assertEquals($assignment->id, $payload['assignment']['id']);
        $this->assertEquals($assignment->boss_id, $payload['assignment']['boss_id']);
        $this->assertEquals($assignment->group_id, $payload['assignment']['group_id']);
    }

    #[Test]
    public function event_assignment_broadcast_with_for_delete_includes_only_id(): void
    {
        $event = Event::factory()->create();
        $assignment = EventAssignment::factory()->for($event)->create();

        $payload = $assignment->broadcastWith('deleted');

        $this->assertEquals(['id' => $assignment->id], $payload);
    }

    #[Test]
    public function event_assignment_create_queues_model_broadcast(): void
    {
        $user = User::factory()->withPermissions('manage-raid-plans')->create();
        $event = Event::factory()->create();
        $group = EventAssignmentGroup::factory()->for($event)->create();

        Queue::fake();

        $this->actingAs($user)->postJson(route('api.events.assignments.store', $event), [
            'group_id' => $group->id,
            'boss_id' => null,
        ])->assertCreated();

        Queue::assertPushed(
            BroadcastEvent::class,
            fn ($job) => $job->event instanceof BroadcastableModelEventOccurred
                && $job->event->model instanceof EventAssignment
                && $job->event->event() === 'created',
        );
    }

    #[Test]
    public function event_assignment_update_queues_model_broadcast(): void
    {
        $user = User::factory()->withPermissions('manage-raid-plans')->create();
        $event = Event::factory()->create();
        $assignment = EventAssignment::factory()->for($event)->create();

        Queue::fake();

        $this->actingAs($user)->patchJson(
            route('api.events.assignments.update', [$event, $assignment]),
            ['left_value' => 'Tank'],
        )->assertNoContent();

        Queue::assertPushed(
            BroadcastEvent::class,
            fn ($job) => $job->event instanceof BroadcastableModelEventOccurred
                && $job->event->model instanceof EventAssignment
                && $job->event->event() === 'updated',
        );
    }

    #[Test]
    public function event_assignment_delete_dispatches_model_broadcast_synchronously(): void
    {
        // Note: "deleted" events are dispatched synchronously by BroadcastableModelEventOccurred
        // (see shouldBroadcastNow()), so they don't go through Queue::fake.
        $user = User::factory()->withPermissions('manage-raid-plans')->create();
        $event = Event::factory()->create();
        $assignment = EventAssignment::factory()->for($event)->create();

        \Illuminate\Support\Facades\Event::fake([BroadcastableModelEventOccurred::class]);

        $this->actingAs($user)->deleteJson(
            route('api.events.assignments.destroy', [$event, $assignment]),
        )->assertNoContent();

        \Illuminate\Support\Facades\Event::assertDispatched(
            BroadcastableModelEventOccurred::class,
            fn ($e) => $e->model instanceof EventAssignment && $e->event() === 'deleted',
        );
    }

    // ─── EventAssignmentGroup model broadcasts ───────────────────────────────

    #[Test]
    public function event_assignment_group_created_broadcasts_on_private_event_channel(): void
    {
        $event = Event::factory()->create();
        $group = EventAssignmentGroup::factory()->for($event)->make();

        $channels = $group->broadcastOn('created');

        $this->assertCount(1, $channels);
        $this->assertInstanceOf(PrivateChannel::class, $channels[0]);
        $this->assertEquals("private-event.{$event->id}", $channels[0]->name);
    }

    #[Test]
    public function event_assignment_group_broadcasts_as_lifecycle_specific_names(): void
    {
        $event = Event::factory()->create();
        $group = EventAssignmentGroup::factory()->for($event)->make();

        $this->assertEquals('EventGroupCreated', $group->broadcastAs('created'));
        $this->assertEquals('EventGroupUpdated', $group->broadcastAs('updated'));
        $this->assertEquals('EventGroupDeleted', $group->broadcastAs('deleted'));
    }

    #[Test]
    public function event_assignment_group_broadcast_with_includes_group_payload(): void
    {
        $event = Event::factory()->create();
        $group = EventAssignmentGroup::factory()->for($event)->create();

        $payload = $group->broadcastWith('created');

        $this->assertArrayHasKey('group', $payload);
        $this->assertEquals($group->id, $payload['group']['id']);
        $this->assertEquals($group->name, $payload['group']['name']);
        $this->assertEquals($group->sort_order, $payload['group']['sort_order']);
    }

    #[Test]
    public function event_assignment_group_broadcast_with_for_delete_includes_only_id(): void
    {
        $event = Event::factory()->create();
        $group = EventAssignmentGroup::factory()->for($event)->create();

        $payload = $group->broadcastWith('deleted');

        $this->assertEquals(['id' => $group->id], $payload);
    }

    #[Test]
    public function event_assignment_group_create_queues_model_broadcast(): void
    {
        $user = User::factory()->withPermissions('manage-raid-plans')->create();
        $event = Event::factory()->create();

        Queue::fake();

        $this->actingAs($user)->postJson(route('api.events.groups.store', $event), [
            'name' => 'New group',
        ])->assertCreated();

        Queue::assertPushed(
            BroadcastEvent::class,
            fn ($job) => $job->event instanceof BroadcastableModelEventOccurred
                && $job->event->model instanceof EventAssignmentGroup
                && $job->event->event() === 'created',
        );
    }

    // ─── Boss model broadcasts ───────────────────────────────────────────────

    #[Test]
    public function boss_updated_broadcasts_on_private_boss_channel(): void
    {
        $boss = Boss::factory()->create();

        $channels = $boss->broadcastOn('updated');

        $this->assertCount(1, $channels);
        $this->assertInstanceOf(PrivateChannel::class, $channels[0]);
        $this->assertEquals("private-boss.{$boss->id}", $channels[0]->name);
    }

    #[Test]
    public function boss_broadcasts_as_boss_strategy_changed_on_update(): void
    {
        $boss = Boss::factory()->create();

        $this->assertEquals('BossStrategyChanged', $boss->broadcastAs('updated'));
    }

    #[Test]
    public function boss_create_and_delete_do_not_broadcast(): void
    {
        $boss = Boss::factory()->create();

        $this->assertEquals([], $boss->broadcastOn('created'));
        $this->assertEquals([], $boss->broadcastOn('deleted'));
    }

    #[Test]
    public function boss_broadcast_with_includes_boss_resource_payload(): void
    {
        $boss = Boss::factory()->create(['notes' => 'Stack on boss.']);

        $payload = $boss->broadcastWith('updated');

        $this->assertArrayHasKey('boss', $payload);
        $this->assertEquals($boss->id, $payload['boss']['id']);
        $this->assertEquals('Stack on boss.', $payload['boss']['notes']);
    }

    #[Test]
    public function boss_strategy_update_queues_model_broadcast(): void
    {
        $user = User::factory()->withPermissions('manage-boss-strategies', 'view-officer-dashboard')->create();
        $boss = Boss::factory()->create();
        $user->refresh();

        Queue::fake();

        $this->actingAs($user)
            ->patch(route('dashboard.boss-strategies.update', $boss), [
                'notes' => 'Updated notes.',
            ])
            ->assertRedirect();

        Queue::assertPushed(
            BroadcastEvent::class,
            fn ($job) => $job->event instanceof BroadcastableModelEventOccurred
                && $job->event->model instanceof Boss
                && $job->event->event() === 'updated',
        );
    }

    // ─── Reorder broadcasts (still controller-level) ─────────────────────────

    #[Test]
    public function assignment_reorder_broadcasts_assignment_changed_with_reordered_action(): void
    {
        $user = User::factory()->withPermissions('manage-raid-plans')->create();
        $event = Event::factory()->create();
        $a = EventAssignment::factory()->for($event)->create();
        $b = EventAssignment::factory()->for($event)->create();

        Queue::fake();

        $this->actingAs($user)->patchJson(
            route('api.events.assignments.reorder', $event),
            ['order' => [$b->id, $a->id]],
        )->assertNoContent();

        Queue::assertPushed(
            BroadcastEvent::class,
            fn ($job) => $job->event instanceof AssignmentChanged
                && $job->event->action === 'reordered'
                && $job->event->payload === ['order' => [$b->id, $a->id]],
        );
    }

    #[Test]
    public function group_reorder_broadcasts_group_changed_with_reordered_action(): void
    {
        $user = User::factory()->withPermissions('manage-raid-plans')->create();
        $event = Event::factory()->create();
        $g1 = EventAssignmentGroup::factory()->for($event)->create();
        $g2 = EventAssignmentGroup::factory()->for($event)->create();

        Queue::fake();

        $this->actingAs($user)->patchJson(
            route('api.events.groups.reorder', $event),
            ['order' => [$g2->id, $g1->id]],
        )->assertNoContent();

        Queue::assertPushed(
            BroadcastEvent::class,
            fn ($job) => $job->event instanceof GroupChanged
                && $job->event->action === 'reordered'
                && $job->event->payload === ['order' => [$g2->id, $g1->id]],
        );
    }

    #[Test]
    public function assignment_changed_reorder_factory_builds_correct_payload(): void
    {
        $event = Event::factory()->create();

        $broadcast = AssignmentChanged::forReorder($event, [3, 1, 2]);

        $this->assertEquals($event->id, $broadcast->eventId);
        $this->assertEquals('reordered', $broadcast->action);
        $this->assertEquals(['order' => [3, 1, 2]], $broadcast->payload);
        $this->assertEquals('AssignmentChanged', $broadcast->broadcastAs());
        $this->assertEquals(['action' => 'reordered', 'order' => [3, 1, 2]], $broadcast->broadcastWith());

        $channels = $broadcast->broadcastOn();
        $this->assertCount(1, $channels);
        $this->assertEquals("private-event.{$event->id}", $channels[0]->name);
    }

    #[Test]
    public function group_changed_reorder_factory_builds_correct_payload(): void
    {
        $event = Event::factory()->create();

        $broadcast = GroupChanged::forReorder($event, [2, 1]);

        $this->assertEquals($event->id, $broadcast->eventId);
        $this->assertEquals('reordered', $broadcast->action);
        $this->assertEquals(['order' => [2, 1]], $broadcast->payload);
        $this->assertEquals('GroupChanged', $broadcast->broadcastAs());
        $this->assertEquals(['action' => 'reordered', 'order' => [2, 1]], $broadcast->broadcastWith());

        $channels = $broadcast->broadcastOn();
        $this->assertCount(1, $channels);
        $this->assertEquals("private-event.{$event->id}", $channels[0]->name);
    }

    // ─── CompositionChanged (still exists for bulk sync) ─────────────────────

    #[Test]
    public function composition_changed_broadcasts_on_private_event_channel(): void
    {
        $event = Event::factory()->create();

        $broadcast = new CompositionChanged($event->id, ['groups' => [], 'bench' => []]);
        $channels = $broadcast->broadcastOn();

        $this->assertCount(1, $channels);
        $this->assertInstanceOf(PrivateChannel::class, $channels[0]);
        $this->assertEquals("private-event.{$event->id}", $channels[0]->name);
    }

    #[Test]
    public function composition_changed_payload_contains_composition(): void
    {
        $event = Event::factory()->create();
        $composition = ['groups' => [['group_number' => 1, 'members' => []]], 'bench' => []];

        $broadcast = new CompositionChanged($event->id, $composition);

        $this->assertEquals('CompositionChanged', $broadcast->broadcastAs());
        $this->assertEquals(['composition' => $composition], $broadcast->broadcastWith());
    }

    // ─── Cache invalidation ──────────────────────────────────────────────────

    #[Test]
    public function event_assignment_save_flushes_raiding_cache(): void
    {
        $event = Event::factory()->create();
        Cache::tags(['raiding', 'events'])->put('canary', 'value', 60);

        EventAssignment::factory()->for($event)->create();

        $this->assertNull(Cache::tags(['raiding', 'events'])->get('canary'));
    }

    #[Test]
    public function event_assignment_group_save_flushes_raiding_cache(): void
    {
        $event = Event::factory()->create();
        Cache::tags(['raiding', 'events'])->put('canary', 'value', 60);

        EventAssignmentGroup::factory()->for($event)->create();

        $this->assertNull(Cache::tags(['raiding', 'events'])->get('canary'));
    }

    #[Test]
    public function boss_save_flushes_raiding_cache(): void
    {
        Cache::tags(['raiding', 'events'])->put('canary', 'value', 60);

        Boss::factory()->create();

        $this->assertNull(Cache::tags(['raiding', 'events'])->get('canary'));
    }

    #[Test]
    public function broadcast_events_implement_flushes_raiding_cache(): void
    {
        $this->assertInstanceOf(FlushesRaidingCache::class, AssignmentChanged::forReorder(Event::factory()->create(), []));
        $this->assertInstanceOf(FlushesRaidingCache::class, GroupChanged::forReorder(Event::factory()->create(), []));
        $this->assertInstanceOf(FlushesRaidingCache::class, new CompositionChanged('1', ['groups' => [], 'bench' => []]));
    }
}
