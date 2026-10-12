<?php

use App\Enums\PlannedAbsenceReason;
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
        Schema::table('planned_absences', function (Blueprint $table) {
            $table->renameColumn('reason', 'notes');
            $table->dropColumn('discord_message_id');
        });

        Schema::table('planned_absences', function (Blueprint $table) {
            $table->text('notes')->nullable()->change();
            $table->string('reason')->default(PlannedAbsenceReason::DEFAULT->value)->after('end_date');
        });
    }
};
