<?php

use App\Enums\Faction;
use Carbon\Carbon;
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
        Schema::create('game_versions', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('realm')->nullable();
            $table->enum('faction', Faction::cases())->nullable();
            $table->dateTime('release_date')->default(Carbon::now());
            $table->string('theme')->nullable();
            $table->string('blizzard_namespace')->nullable();
            $table->integer('warcraftlogs_guild')->nullable();
            $table->integer('warcraftlogs_expansion')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('game_versions');
    }
};
