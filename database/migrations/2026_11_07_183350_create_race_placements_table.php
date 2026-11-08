<?php

use App\Models\Character;
use App\Models\GameVersion;
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
        Schema::create('race_placements', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(GameVersion::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(Character::class)->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->timestamp('reached_at');
            $table->timestamps();

            $table->unique(['game_version_id', 'character_id']);
            $table->index(['game_version_id', 'position']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('race_placements');
    }
};
