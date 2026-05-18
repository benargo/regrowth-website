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
        Schema::table('events', function (Blueprint $table) {
            $table->string('raid_helper_event_id')->nullable()->change();
            $table->string('channel_id')->nullable()->change();
            $table->dateTime('start_time')->nullable()->change();
            $table->dateTime('end_time')->nullable()->change();
            $table->boolean('is_template')->default(false)->after('channel_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('is_template');

            // Given the risk of error, we will not attempt to revert the nullability changes, as this could lead to
            // data loss if there are any records with null values in these columns after the migration has been run.
        });
    }
};
