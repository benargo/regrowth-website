<?php

use App\Models\Character;
use App\Models\Event;
use App\Models\TBC\Raid;
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
        Schema::create('events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('raid_helper_event_id')->unique();
            $table->string('title');
            $table->dateTime('start_time');
            $table->dateTime('end_time');
            $table->string('channel_id');
            $table->timestamps();
        });

        Schema::create('pivot_events_characters', function (Blueprint $table) {
            $table->foreignIdFor(Event::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(Character::class)->constrained()->cascadeOnDelete();
            $table->tinyInteger('slot_number')->nullable();
            $table->tinyInteger('group_number')->nullable();
            $table->boolean('is_confirmed')->default(false);
            $table->boolean('is_leader')->default(false);
            $table->boolean('is_loot_councillor')->default(false);
            $table->boolean('is_loot_master')->default(false);
            $table->timestamps();
            $table->primary(['event_id', 'character_id']);
        });

        Schema::create('pivot_events_raids', function (Blueprint $table) {
            $table->foreignIdFor(Event::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(Raid::class)->constrained()->cascadeOnDelete();
            $table->primary(['event_id', 'raid_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pivot_events_raids');
        Schema::dropIfExists('pivot_events_characters');
        Schema::dropIfExists('events');
    }
};
