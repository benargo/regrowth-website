<?php

use App\Models\GameVersion;
use App\Models\PlayableRace;
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
        Schema::create('pivot_game_versions_playable_races', function (Blueprint $table) {
            $table->foreignIdFor(GameVersion::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(PlayableRace::class)->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['game_version_id', 'playable_race_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pivot_game_versions_playable_races');
    }
};
