<?php

use App\Models\GameVersion;
use App\Models\PlayableClass;
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
        Schema::create('pivot_game_versions_playable_classes', function (Blueprint $table) {
            $table->foreignIdFor(GameVersion::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(PlayableClass::class)->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['game_version_id', 'playable_class_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pivot_game_versions_playable_classes');
    }
};
