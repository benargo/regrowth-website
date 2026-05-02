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
        Schema::create('raid_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('raid_helper_event_id')->unique();
            $table->string('title');
            $table->dateTime('start_time');
            $table->dateTime('end_time');
            $table->string('channel_id');
            $table->timestamps();
        });

        Schema::create('pivot_raid_events_characters', function (Blueprint $table) {
            $table->foreignUuid('event_id');
            $table->foreignId('character_id');
            $table->tinyInteger('slot_number')->nullable();
            $table->tinyInteger('group_number')->nullable();
            $table->boolean('is_confirmed')->default(false);
            $table->boolean('is_leader')->default(false);
            $table->boolean('is_loot_councillor')->default(false);
            $table->boolean('is_loot_master')->default(false);
            $table->timestamps();
            $table->primary(['event_id', 'character_id']);
            $table->foreign('event_id')->references('id')->on('raid_events')->onDelete('cascade');
            $table->foreign('character_id')->references('id')->on('characters')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pivot_raid_events_characters');
        Schema::dropIfExists('raid_events');
    }
};
