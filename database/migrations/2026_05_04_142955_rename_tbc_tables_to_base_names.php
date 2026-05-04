<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop FKs referencing tbc_phases before renaming it
        Schema::table('tbc_raids', function (Blueprint $table) {
            $table->dropForeign(['phase_id']);
        });
        Schema::table('wcl_guild_tags', function (Blueprint $table) {
            $table->dropForeign(['tbc_phase_id']);
        });

        // Drop FKs referencing tbc_raids before renaming it
        Schema::table('tbc_bosses', function (Blueprint $table) {
            $table->dropForeign(['raid_id']);
        });
        Schema::table('lootcouncil_items', function (Blueprint $table) {
            $table->dropForeign(['raid_id']);
        });

        // Drop FKs referencing tbc_bosses before renaming it
        Schema::table('lootcouncil_items', function (Blueprint $table) {
            $table->dropForeign(['boss_id']);
        });

        // Drop FK referencing tbc_raids on pivot_events_raids
        Schema::table('pivot_events_raids', function (Blueprint $table) {
            $table->dropForeign(['raid_id']);
        });

        // Rename tables
        Schema::rename('tbc_phases', 'phases');
        Schema::rename('tbc_raids', 'raids');
        Schema::rename('tbc_bosses', 'bosses');
        Schema::rename('tbc_daily_quests', 'daily_quests');

        // Recreate FKs with new table names
        Schema::table('raids', function (Blueprint $table) {
            $table->foreign('phase_id')->references('id')->on('phases');
        });
        Schema::table('bosses', function (Blueprint $table) {
            $table->foreign('raid_id')->references('id')->on('raids');
        });
        Schema::table('lootcouncil_items', function (Blueprint $table) {
            $table->foreign('raid_id')->references('id')->on('raids');
            $table->foreign('boss_id')->references('id')->on('bosses')->nullOnDelete();
        });
        Schema::table('wcl_guild_tags', function (Blueprint $table) {
            $table->foreign('tbc_phase_id')->references('id')->on('phases')->nullOnDelete();
        });
        Schema::table('pivot_events_raids', function (Blueprint $table) {
            $table->foreign('raid_id')->references('id')->on('raids')->cascadeOnDelete();
        });

        // Drop the daily quest notifications table as it's no longer needed and references the old daily quests table
        // This may have been missed previously, but it's important to drop it anyway.
        // No need to recreate it in the down() method since it was already dropped in a separate migration.
        Schema::dropIfExists('tbc_daily_quest_notifications');
    }

    public function down(): void
    {
        // Drop FKs before renaming back
        Schema::table('raids', function (Blueprint $table) {
            $table->dropForeign(['phase_id']);
        });
        Schema::table('wcl_guild_tags', function (Blueprint $table) {
            $table->dropForeign(['tbc_phase_id']);
        });
        Schema::table('bosses', function (Blueprint $table) {
            $table->dropForeign(['raid_id']);
        });
        Schema::table('lootcouncil_items', function (Blueprint $table) {
            $table->dropForeign(['raid_id']);
            $table->dropForeign(['boss_id']);
        });
        Schema::table('pivot_events_raids', function (Blueprint $table) {
            $table->dropForeign(['raid_id']);
        });

        // Rename back
        Schema::rename('phases', 'tbc_phases');
        Schema::rename('raids', 'tbc_raids');
        Schema::rename('bosses', 'tbc_bosses');
        Schema::rename('daily_quests', 'tbc_daily_quests');

        // Recreate original FKs
        Schema::table('tbc_raids', function (Blueprint $table) {
            $table->foreign('phase_id')->references('id')->on('tbc_phases');
        });
        Schema::table('tbc_bosses', function (Blueprint $table) {
            $table->foreign('raid_id')->references('id')->on('tbc_raids');
        });
        Schema::table('lootcouncil_items', function (Blueprint $table) {
            $table->foreign('raid_id')->references('id')->on('tbc_raids');
            $table->foreign('boss_id')->references('id')->on('tbc_bosses')->nullOnDelete();
        });
        Schema::table('wcl_guild_tags', function (Blueprint $table) {
            $table->foreign('tbc_phase_id')->references('id')->on('tbc_phases')->nullOnDelete();
        });
        Schema::table('pivot_events_raids', function (Blueprint $table) {
            $table->foreign('raid_id')->references('id')->on('tbc_raids')->cascadeOnDelete();
        });
    }
};
