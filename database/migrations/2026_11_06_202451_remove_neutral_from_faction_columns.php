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
        Schema::table('game_versions', function (Blueprint $table) {
            $table->enum('faction', ['Alliance', 'Horde'])->nullable()->change();
        });

        Schema::table('playable_races', function (Blueprint $table) {
            $table->enum('faction', ['Alliance', 'Horde'])->nullable()->default(null)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('game_versions', function (Blueprint $table) {
            $table->enum('faction', ['Alliance', 'Horde', 'Neutral'])->nullable()->change();
        });

        Schema::table('playable_races', function (Blueprint $table) {
            $table->enum('faction', ['Alliance', 'Horde', 'Neutral'])->default('Neutral')->change();
        });
    }
};
