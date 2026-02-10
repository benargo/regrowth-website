<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * This migration is no longer needed, but we want to keep it around for reference in case we need to add this column in the future.
     */
    public function up(): void
    {
        // Schema::table('characters', function (Blueprint $table) {
        //     $table->timestamp('reached_level_cap_at')->nullable()->after('is_loot_councillor');
        // });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('characters', function (Blueprint $table) {
            $table->dropColumn('reached_level_cap_at');
        });
    }
};
