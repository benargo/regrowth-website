<?php

namespace Tests\Unit\Http\Resources;

use App\Http\Resources\EventAssignmentsCollection;
use App\Models\Event;
use App\Models\EventAssignment;
use App\Models\EventAssignmentGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EventAssignmentsCollectionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_returns_groups_and_ungrouped_keys(): void
    {
        $array = (new EventAssignmentsCollection(collect()))->toArray(new Request);

        $this->assertArrayHasKey('groups', $array);
        $this->assertArrayHasKey('ungrouped', $array);
    }

    #[Test]
    public function it_returns_empty_groups_and_ungrouped_when_no_assignments(): void
    {
        $array = (new EventAssignmentsCollection(collect()))->toArray(new Request);

        $this->assertSame([], $array['groups']);
        $this->assertSame([], $array['ungrouped']);
    }

    #[Test]
    public function it_places_ungrouped_assignments_in_ungrouped_key(): void
    {
        $event = Event::factory()->create();
        $assignments = EventAssignment::factory()->count(2)->for($event)->create();
        $assignments->load('group');

        $array = (new EventAssignmentsCollection($assignments))->toArray(new Request);

        $this->assertCount(2, $array['ungrouped']);
        $this->assertSame([], $array['groups']);
    }

    #[Test]
    public function it_places_grouped_assignments_inside_their_group(): void
    {
        $event = Event::factory()->create();
        $group = EventAssignmentGroup::factory()->for($event)->create();
        $assignments = EventAssignment::factory()->count(2)->for($event)->forGroup($group)->create();
        $assignments->load('group');

        $array = (new EventAssignmentsCollection($assignments))->toArray(new Request);

        $this->assertCount(1, $array['groups']);
        $this->assertCount(2, $array['groups'][0]['assignments']);
        $this->assertSame([], $array['ungrouped']);
    }

    #[Test]
    public function it_returns_correct_group_shape(): void
    {
        $event = Event::factory()->create();
        $group = EventAssignmentGroup::factory()->for($event)->create(['title' => 'Healers', 'sort_order' => 3]);
        $assignments = EventAssignment::factory()->for($event)->forGroup($group)->create();
        $assignments = EventAssignment::whereKey($assignments->id)->with('group')->get();

        $array = (new EventAssignmentsCollection($assignments))->toArray(new Request);

        $groupData = $array['groups'][0];
        $this->assertSame($group->id, $groupData['id']);
        $this->assertSame('Healers', $groupData['name']);
        $this->assertSame(3, $groupData['sort_order']);
        $this->assertArrayHasKey('assignments', $groupData);
    }

    #[Test]
    public function it_separates_grouped_and_ungrouped_assignments(): void
    {
        $event = Event::factory()->create();
        $group = EventAssignmentGroup::factory()->for($event)->create();

        EventAssignment::factory()->for($event)->forGroup($group)->create();
        EventAssignment::factory()->for($event)->create();

        $assignments = EventAssignment::where('event_id', $event->id)->with('group')->get();

        $array = (new EventAssignmentsCollection($assignments))->toArray(new Request);

        $this->assertCount(1, $array['groups']);
        $this->assertCount(1, $array['groups'][0]['assignments']);
        $this->assertCount(1, $array['ungrouped']);
    }

    #[Test]
    public function it_sorts_groups_by_sort_order(): void
    {
        $event = Event::factory()->create();
        $groupB = EventAssignmentGroup::factory()->for($event)->create(['sort_order' => 2]);
        $groupA = EventAssignmentGroup::factory()->for($event)->create(['sort_order' => 1]);

        EventAssignment::factory()->for($event)->forGroup($groupB)->create();
        EventAssignment::factory()->for($event)->forGroup($groupA)->create();

        $assignments = EventAssignment::where('event_id', $event->id)->with('group')->get();

        $array = (new EventAssignmentsCollection($assignments))->toArray(new Request);

        $this->assertSame($groupA->id, $array['groups'][0]['id']);
        $this->assertSame($groupB->id, $array['groups'][1]['id']);
    }

    #[Test]
    public function it_sorts_assignments_within_a_group_by_sort_order(): void
    {
        $event = Event::factory()->create();
        $group = EventAssignmentGroup::factory()->for($event)->create();

        $second = EventAssignment::factory()->for($event)->forGroup($group)->create(['sort_order' => 2]);
        $first = EventAssignment::factory()->for($event)->forGroup($group)->create(['sort_order' => 1]);

        $assignments = EventAssignment::whereIn('id', [$second->id, $first->id])->with('group')->get();

        $array = (new EventAssignmentsCollection($assignments))->toArray(new Request);

        $this->assertSame($first->id, $array['groups'][0]['assignments'][0]['id']);
        $this->assertSame($second->id, $array['groups'][0]['assignments'][1]['id']);
    }
}
