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
        Schema::table('guild_ranks', function (Blueprint $table) {
            $table->dropUnique('guild_ranks_position_unique');
        });

        Schema::table('guild_ranks', function (Blueprint $table) {
            $table->renameColumn('position', 'sort_order');
        });

        Schema::table('guild_ranks', function (Blueprint $table) {
            $table->unsignedSmallInteger('sort_order')->nullable(false)->default(0)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('guild_ranks', function (Blueprint $table) {
            $table->integer('sort_order')->nullable(false)->change();
        });

        Schema::table('guild_ranks', function (Blueprint $table) {
            $table->renameColumn('sort_order', 'position');
        });

        Schema::table('guild_ranks', function (Blueprint $table) {
            $table->unique('position');
        });
    }
};
