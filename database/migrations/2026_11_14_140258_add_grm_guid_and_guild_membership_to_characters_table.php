<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add the GRM GUID, guild membership and rank and level evidence columns to characters.
     */
    public function up(): void
    {
        Schema::table('characters', function (Blueprint $table) {
            $table->string('grm_guid', 40)->nullable()->after('id');
            $table->timestamp('left_guild_at')->nullable();
            $table->timestamp('rank_observed_at')->nullable()->after('rank_id');
            $table->string('rank_source', 16)->nullable()->after('rank_observed_at');
            $table->timestamp('level_observed_at')->nullable()->after('level');
            $table->unique(['game_version_id', 'grm_guid']);
        });
    }

    /**
     * Drop the GRM GUID, guild membership and evidence columns.
     */
    public function down(): void
    {
        Schema::table('characters', function (Blueprint $table) {
            $table->dropUnique(['game_version_id', 'grm_guid']);
            $table->dropColumn(['grm_guid', 'left_guild_at', 'rank_observed_at', 'rank_source', 'level_observed_at']);
        });
    }
};
