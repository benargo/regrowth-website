<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('event_assignment_groups', 'boss_id')) {
            Schema::table('event_assignment_groups', function (Blueprint $table) {
                $table->foreignId('boss_id')->nullable()->after('event_id')
                    ->constrained('bosses')->cascadeOnDelete();
            });

            try {
                DB::statement('
                    UPDATE event_assignment_groups
                    SET boss_id = (
                        SELECT MIN(event_assignments.boss_id)
                        FROM event_assignments
                        WHERE event_assignments.group_id = event_assignment_groups.id
                    )
                ');
            } catch (Throwable $e) {
                // Silently ignore — backfill is best-effort.
            }
        }

        Schema::table('event_assignment_groups', function (Blueprint $table) {
            if (! Schema::hasIndex('event_assignment_groups', 'event_assignment_groups_id_event_boss_unique')) {
                $table->unique(['event_id', 'id', 'boss_id'], 'event_assignment_groups_id_event_boss_unique');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // The composite unique index leads with event_id, so MySQL uses it to support
        // event_assignment_groups_event_id_foreign when no dedicated single-column index exists.
        // Drop that FK first, remove the unique index, then drop boss_id (which restores the FK via dropConstrainedForeignId).
        Schema::table('event_assignment_groups', function (Blueprint $table) {
            if (collect(Schema::getForeignKeys('event_assignment_groups'))->pluck('name')->contains('event_assignment_groups_event_id_foreign')) {
                $table->dropForeign('event_assignment_groups_event_id_foreign');
            }
        });

        Schema::table('event_assignment_groups', function (Blueprint $table) {
            if (Schema::hasIndex('event_assignment_groups', 'event_assignment_groups_id_event_boss_unique')) {
                $table->dropUnique('event_assignment_groups_id_event_boss_unique');
            }
        });

        Schema::table('event_assignment_groups', function (Blueprint $table) {
            if (! collect(Schema::getForeignKeys('event_assignment_groups'))->pluck('name')->contains('event_assignment_groups_event_id_foreign')) {
                $table->foreign('event_id', 'event_assignment_groups_event_id_foreign')
                    ->references('id')
                    ->on('events')
                    ->cascadeOnDelete();
            }
        });

        Schema::table('event_assignment_groups', function (Blueprint $table) {
            if (Schema::hasColumn('event_assignment_groups', 'boss_id')) {
                $table->dropConstrainedForeignId('boss_id');
            }
        });
    }
};
