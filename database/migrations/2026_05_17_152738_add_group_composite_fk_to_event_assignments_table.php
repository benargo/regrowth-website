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
            $table->foreign(['event_id', 'group_id', 'boss_id'], 'event_assignments_event_group_boss_fkey')
                ->references(['event_id', 'id', 'boss_id'])
                ->on('event_assignment_groups')
                ->cascadeOnDelete();

            $table->index(['event_id', 'group_id', 'boss_id'], 'event_assignments_event_group_boss_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('event_assignments', function (Blueprint $table) {
            $table->dropIndex('event_assignments_event_group_boss_idx');
            $table->dropForeign('event_assignments_event_group_boss_fkey');
        });
    }
};
