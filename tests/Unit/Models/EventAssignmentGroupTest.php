<?php

namespace Tests\Unit\Models;

use App\Models\Boss;
use App\Models\Event;
use App\Models\EventAssignment;
use App\Models\EventAssignmentGroup;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\ModelTestCase;

class EventAssignmentGroupTest extends ModelTestCase
{
    protected function modelClass(): string
    {
        return EventAssignmentGroup::class;
    }

    // ============ Schema / config ============

    #[Test]
    public function it_uses_event_assignment_groups_table(): void
    {
        $model = new EventAssignmentGroup;

        $this->assertSame('event_assignment_groups', $model->getTable());
    }

    #[Test]
    public function it_uses_auto_incrementing_integer_primary_key(): void
    {
        $model = new EventAssignmentGroup;

        $this->assertSame('id', $model->getKeyName());
        $this->assertTrue($model->getIncrementing());
        $this->assertSame('int', $model->getKeyType());
    }

    #[Test]
    public function it_has_expected_fillable_attributes(): void
    {
        $model = new EventAssignmentGroup;

        $this->assertFillable($model, [
            'event_id',
            'boss_id',
            'name',
            'notes',
            'sort_order',
        ]);
    }

    #[Test]
    public function it_has_expected_hidden_attributes(): void
    {
        $model = new EventAssignmentGroup;

        $this->assertHidden($model, [
            'event_id',
            'created_at',
            'updated_at',
        ]);
    }

    #[Test]
    public function it_has_timestamps(): void
    {
        $model = new EventAssignmentGroup;

        $this->assertTrue($model->usesTimestamps());
    }

    // ============ Default attributes ============

    #[Test]
    public function it_defaults_name_to_new_group(): void
    {
        $model = new EventAssignmentGroup;

        $this->assertSame('New group', $model->name);
    }

    // ============ Factory / persistence ============

    #[Test]
    public function factory_creates_valid_model(): void
    {
        $group = $this->create();

        $this->assertModelExists($group);
        $this->assertNotNull($group->event_id);
        $this->assertNotEmpty($group->name);
    }

    #[Test]
    public function it_can_be_created_without_notes(): void
    {
        $group = $this->create(['notes' => null]);

        $this->assertModelExists($group);
        $this->assertNull($group->notes);
    }

    #[Test]
    public function it_can_be_created_with_notes(): void
    {
        $group = $this->create(['notes' => 'Focus the adds first.']);

        $this->assertModelExists($group);
        $this->assertNotNull($group->notes);
    }

    // ============ Sort order ============

    #[Test]
    public function it_defaults_sort_order_to_zero(): void
    {
        $group = $this->create(['sort_order' => 0]);

        $this->assertSame(0, $group->sort_order);
    }

    #[Test]
    public function it_can_be_created_with_a_sort_order(): void
    {
        $group = $this->create(['sort_order' => 5]);

        $this->assertSame(5, $group->sort_order);
    }

    // ============ Notes accessor ============

    #[Test]
    public function notes_are_rendered_as_markdown(): void
    {
        $group = $this->create(['notes' => '**bold**']);

        $this->assertStringContainsString('<strong>bold</strong>', $group->notes);
    }

    #[Test]
    public function notes_returns_null_when_not_set(): void
    {
        $group = $this->create(['notes' => null]);

        $this->assertNull($group->notes);
    }

    // ============ Relationships ============

    #[Test]
    public function it_belongs_to_an_event(): void
    {
        $event = Event::factory()->create();
        $group = $this->create(['event_id' => $event->id]);

        $this->assertRelation($group, 'event', BelongsTo::class);
        $this->assertTrue($group->event->is($event));
    }

    #[Test]
    public function it_belongs_to_a_boss(): void
    {
        $boss = Boss::factory()->create();
        $group = $this->create(['boss_id' => $boss->id]);

        $this->assertRelation($group, 'boss', BelongsTo::class);
        $this->assertTrue($group->boss->is($boss));
    }

    #[Test]
    public function it_can_be_created_with_a_boss(): void
    {
        $boss = Boss::factory()->create();
        $group = $this->create(['boss_id' => $boss->id]);

        $this->assertModelExists($group);
        $this->assertSame($boss->id, $group->boss_id);
    }

    #[Test]
    public function it_can_be_created_without_a_boss(): void
    {
        $group = $this->create(['boss_id' => null]);

        $this->assertModelExists($group);
        $this->assertNull($group->boss_id);
    }

    #[Test]
    public function it_has_many_assignments(): void
    {
        $group = $this->create();
        EventAssignment::factory()->count(2)->create([
            'event_id' => $group->event_id,
            'group_id' => $group->id,
        ]);

        $relation = $group->assignments();
        $this->assertInstanceOf(HasMany::class, $relation);
        $this->assertCount(2, $group->assignments);
    }

    // ============ Cascade deletes ============

    #[Test]
    public function deleting_event_cascades_to_groups(): void
    {
        $event = Event::factory()->create();
        $group = $this->create(['event_id' => $event->id]);

        $event->delete();

        $this->assertDatabaseMissing('event_assignment_groups', ['id' => $group->id]);
    }

    #[Test]
    public function deleting_boss_cascades_to_groups(): void
    {
        $boss = Boss::factory()->create();
        $group = $this->create(['boss_id' => $boss->id]);

        $boss->delete();

        $this->assertDatabaseMissing('event_assignment_groups', ['id' => $group->id]);
    }

    #[Test]
    public function deleting_group_cascades_to_assignments(): void
    {
        $group = $this->create();
        $assignment = EventAssignment::factory()->create([
            'event_id' => $group->event_id,
            'group_id' => $group->id,
        ]);

        $group->delete();

        $this->assertDatabaseMissing('event_assignments', ['id' => $assignment->id]);
    }
}
