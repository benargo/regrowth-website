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

        Schema::table('event_assignment_groups', function (Blueprint $table) {
            $table->unique(['event_id', 'id', 'boss_id'], 'event_assignment_groups_id_event_boss_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('event_assignment_groups', function (Blueprint $table) {
            $table->dropUnique('event_assignment_groups_id_event_boss_unique');
            $table->dropConstrainedForeignId('boss_id');
        });
    }
};
