<?php

namespace Tests\Feature\Database\Migrations;

use App\Models\Boss;
use App\Models\Event;
use App\Models\EventAssignmentGroup;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ShiftAndBackfillSortOrderMigrationTest extends TestCase
{
    use RefreshDatabase;

    protected function migration(): Migration
    {
        return require database_path('migrations/2026_08_21_182409_shift_and_backfill_sort_order_for_sortable_migration.php');
    }

    // ==================== up ====================

    #[Test]
    public function up_shifts_every_group_up_by_one(): void
    {
        $event = Event::factory()->create();
        $zero = EventAssignmentGroup::factory()->for($event)->create();
        $zero->sort_order = 0;
        $zero->saveQuietly();
        $five = EventAssignmentGroup::factory()->for($event)->create();
        $five->sort_order = 5;
        $five->saveQuietly();

        $this->migration()->up();

        $this->assertSame(1, $zero->fresh()->sort_order);
        $this->assertSame(6, $five->fresh()->sort_order);
    }

    #[Test]
    public function up_shifts_non_null_assignments_up_by_one(): void
    {
        $event = Event::factory()->create();
        $id = $this->insertAssignment($event->id, sortOrder: 3);

        $this->migration()->up();

        $this->assertSame(4, $this->assignmentSortOrder($id));
    }

    #[Test]
    public function up_backfills_null_assignments_sequentially_within_a_scope(): void
    {
        $this->makeAssignmentSortOrderNullable();

        $event = Event::factory()->create();
        $first = $this->insertAssignment($event->id, sortOrder: null);
        $second = $this->insertAssignment($event->id, sortOrder: null);
        $third = $this->insertAssignment($event->id, sortOrder: null);

        $this->migration()->up();

        $this->assertSame(1, $this->assignmentSortOrder($first));
        $this->assertSame(2, $this->assignmentSortOrder($second));
        $this->assertSame(3, $this->assignmentSortOrder($third));
    }

    #[Test]
    public function up_restarts_the_null_backfill_counter_for_each_scope(): void
    {
        $this->makeAssignmentSortOrderNullable();

        $event = Event::factory()->create();
        $otherEvent = Event::factory()->create();
        $boss = Boss::factory()->create();
        $group = EventAssignmentGroup::factory()->for($event)->create();

        $eventScopeA = $this->insertAssignment($event->id, sortOrder: null);
        $eventScopeB = $this->insertAssignment($event->id, sortOrder: null);
        $bossScope = $this->insertAssignment($event->id, bossId: $boss->id, sortOrder: null);
        $groupScope = $this->insertAssignment($event->id, groupId: $group->id, sortOrder: null);
        $otherEventScope = $this->insertAssignment($otherEvent->id, sortOrder: null);

        $this->migration()->up();

        $this->assertSame(1, $this->assignmentSortOrder($eventScopeA));
        $this->assertSame(2, $this->assignmentSortOrder($eventScopeB));
        $this->assertSame(1, $this->assignmentSortOrder($bossScope));
        $this->assertSame(1, $this->assignmentSortOrder($groupScope));
        $this->assertSame(1, $this->assignmentSortOrder($otherEventScope));
    }

    #[Test]
    public function up_leaves_null_assignments_from_other_scopes_out_of_the_running_count(): void
    {
        $this->makeAssignmentSortOrderNullable();

        $event = Event::factory()->create();
        $group = EventAssignmentGroup::factory()->for($event)->create();

        // Interleaved insertion order across two scopes; each scope must still
        // number 1..n by id, independent of the other scope's rows.
        $eventFirst = $this->insertAssignment($event->id, sortOrder: null);
        $groupFirst = $this->insertAssignment($event->id, groupId: $group->id, sortOrder: null);
        $eventSecond = $this->insertAssignment($event->id, sortOrder: null);
        $groupSecond = $this->insertAssignment($event->id, groupId: $group->id, sortOrder: null);

        $this->migration()->up();

        $this->assertSame(1, $this->assignmentSortOrder($eventFirst));
        $this->assertSame(2, $this->assignmentSortOrder($eventSecond));
        $this->assertSame(1, $this->assignmentSortOrder($groupFirst));
        $this->assertSame(2, $this->assignmentSortOrder($groupSecond));
    }

    #[Test]
    public function up_backfills_null_assignments_without_touching_the_non_null_rows_it_already_shifted(): void
    {
        $this->makeAssignmentSortOrderNullable();

        $event = Event::factory()->create();
        $shifted = $this->insertAssignment($event->id, sortOrder: 1);
        $backfilled = $this->insertAssignment($event->id, sortOrder: null);

        $this->migration()->up();

        $this->assertSame(2, $this->assignmentSortOrder($shifted));
        $this->assertSame(1, $this->assignmentSortOrder($backfilled));
    }

    #[Test]
    public function down_leaves_a_group_already_at_the_unsigned_floor_unchanged(): void
    {
        $event = Event::factory()->create();
        $group = EventAssignmentGroup::factory()->for($event)->create();
        $group->sort_order = 0;
        $group->saveQuietly();

        $this->migration()->down();

        $this->assertSame(0, $group->fresh()->sort_order);
    }

    #[Test]
    public function down_decrements_a_group_above_the_unsigned_floor(): void
    {
        $event = Event::factory()->create();
        $group = EventAssignmentGroup::factory()->for($event)->create();
        $group->sort_order = 2;
        $group->saveQuietly();

        $this->migration()->down();

        $this->assertSame(1, $group->fresh()->sort_order);
    }

    // ==================== helpers ====================

    /**
     * Roll the schema back to the pre-migration state, where sort_order was
     * still nullable, so NULL rows the backfill targets can be inserted.
     */
    private function makeAssignmentSortOrderNullable(): void
    {
        Schema::table('event_assignments', function (Blueprint $table) {
            $table->unsignedSmallInteger('sort_order')->nullable()->change();
        });
    }

    /**
     * Insert an event_assignments row directly, bypassing the Sortable trait so
     * a NULL sort_order can be persisted for the backfill under test.
     */
    private function insertAssignment(
        string $eventId,
        ?int $bossId = null,
        ?int $groupId = null,
        ?int $sortOrder = null,
    ): int {
        return DB::table('event_assignments')->insertGetId([
            'event_id' => $eventId,
            'boss_id' => $bossId,
            'group_id' => $groupId,
            'sort_order' => $sortOrder,
            'left_value' => 'left',
            'right_value' => 'right',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function assignmentSortOrder(int $id): ?int
    {
        $value = DB::table('event_assignments')->where('id', $id)->value('sort_order');

        return $value === null ? null : (int) $value;
    }
}
