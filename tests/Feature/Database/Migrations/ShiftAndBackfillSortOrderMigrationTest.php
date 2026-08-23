<?php

namespace Tests\Feature\Database\Migrations;

use App\Models\Event;
use App\Models\EventAssignmentGroup;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ShiftAndBackfillSortOrderMigrationTest extends TestCase
{
    use RefreshDatabase;

    protected function migration(): Migration
    {
        return require database_path('migrations/2026_08_21_182409_shift_and_backfill_sort_order_for_sortable_migration.php');
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
}
