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
        Schema::table('pivot_events_characters', function (Blueprint $table) {
            $table->boolean('is_benched')->default(false)->after('is_loot_master');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pivot_events_characters', function (Blueprint $table) {
            $table->dropColumn('is_benched');
        });
    }
};
