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
        Schema::create('tbc_daily_quest_notifications', function (Blueprint $table) {
            $table->id();
            $table->dateTime('date')->unique(); // Unique constraint
            $table->string('discord_message_id')->nullable(); // Nullable initially

            // Quest foreign keys
            $table->foreignId('cooking_quest_id')->nullable()->constrained('tbc_daily_quests');
            $table->foreignId('fishing_quest_id')->nullable()->constrained('tbc_daily_quests');
            $table->foreignId('dungeon_quest_id')->nullable()->constrained('tbc_daily_quests');
            $table->foreignId('heroic_quest_id')->nullable()->constrained('tbc_daily_quests');
            $table->foreignId('pvp_quest_id')->nullable()->constrained('tbc_daily_quests');

            // User foreign keys - DEFINE COLUMNS FIRST
            $table->string('sent_by_user_id')->nullable();
            $table->foreign('sent_by_user_id')->references('id')->on('users')->nullOnDelete();
            $table->string('updated_by_user_id')->nullable();
            $table->foreign('updated_by_user_id')->references('id')->on('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tbc_daily_quest_notifications');
    }
};
