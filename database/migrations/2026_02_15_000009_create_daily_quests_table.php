<?php

use App\Enums\Instance;
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
        Schema::create('tbc_daily_quests', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('type', ['Cooking', 'Dungeon', 'Fishing', 'Heroic', 'PvP']);
            $table->enum('instance', [
                // Hellfire Citadel
                Instance::HellfireRamparts->value,
                Instance::BloodFurnace->value,
                Instance::ShatteredHalls->value,
                // Coilfang Reservoir
                Instance::SlavePens->value,
                Instance::Underbog->value,
                Instance::Steamvault->value,
                // Auchindoun
                Instance::AuchenaiCrypts->value,
                Instance::ManaTombs->value,
                Instance::SethekkHalls->value,
                Instance::ShadowLabyrinth->value,
                // Caverns of Time
                Instance::OldHillsbradFoothills->value,
                Instance::BlackMorass->value,
                // Tempest Keep
                Instance::Mechanar->value,
                Instance::Botanica->value,
                Instance::Arcatraz->value,
                // The Isle of Quel'Danas
                Instance::MagistersTerrace->value,
                // Battlegrounds
                Instance::AlteracValley->value,
                Instance::ArathiBasin->value,
                Instance::EyeOfTheStorm->value,
                Instance::WarsongGulch->value,
            ])->nullable();
            $table->enum('mode', ['Normal', 'Heroic'])->nullable();
            $table->json('rewards');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tbc_daily_quests');
    }
};
