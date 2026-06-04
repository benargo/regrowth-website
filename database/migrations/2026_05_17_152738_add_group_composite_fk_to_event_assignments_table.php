<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('event_assignments', function (Blueprint $table) {
            if (! Schema::hasIndex('event_assignments', 'event_assignments_event_group_boss_idx')) {
                $table->index(['event_id', 'group_id', 'boss_id'], 'event_assignments_event_group_boss_idx');
            }

            if (! collect(Schema::getForeignKeys('event_assignments'))->pluck('name')->contains('event_assignments_event_group_boss_fkey')) {
                $table->foreign(['event_id', 'group_id', 'boss_id'], 'event_assignments_event_group_boss_fkey')
                    ->references(['event_id', 'id', 'boss_id'])
                    ->on('event_assignment_groups')
                    ->cascadeOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('event_assignments', function (Blueprint $table) {
            if (collect(Schema::getForeignKeys('event_assignments'))->pluck('name')->contains('event_assignments_event_group_boss_fkey')) {
                $table->dropForeign('event_assignments_event_group_boss_fkey');
            }
        });

        // The composite index leads with event_id, so MySQL uses it to support
        // event_assignments_event_id_foreign when no dedicated single-column index exists.
        // Drop that FK first, remove the composite index, then restore the FK.
        Schema::table('event_assignments', function (Blueprint $table) {
            if (collect(Schema::getForeignKeys('event_assignments'))->pluck('name')->contains('event_assignments_event_id_foreign')) {
                $table->dropForeign('event_assignments_event_id_foreign');
            }
        });

        Schema::table('event_assignments', function (Blueprint $table) {
            if (Schema::hasIndex('event_assignments', 'event_assignments_event_group_boss_idx')) {
                $table->dropIndex('event_assignments_event_group_boss_idx');
            }
        });

        Schema::table('event_assignments', function (Blueprint $table) {
            if (! collect(Schema::getForeignKeys('event_assignments'))->pluck('name')->contains('event_assignments_event_id_foreign')) {
                $table->foreign('event_id', 'event_assignments_event_id_foreign')
                    ->references('id')
                    ->on('events')
                    ->cascadeOnDelete();
            }
        });
    }
};
