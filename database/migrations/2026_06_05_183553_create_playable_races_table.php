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
        Schema::create('playable_races', function (Blueprint $table) {
            $table->id();
            $table->string('name', length: 32)->unique();
        });

        Schema::table('characters', function (Blueprint $table) {
            $table->dropColumn('playable_race');
        });

        Schema::table('characters', function (Blueprint $table) {
            $table->foreignId('playable_race_id')->after('playable_class_id')->nullable()->constrained('playable_races')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('characters', function (Blueprint $table) {
            $table->dropForeign(['playable_race_id']);
            $table->dropColumn('playable_race_id');
        });

        Schema::table('characters', function (Blueprint $table) {
            $table->json('playable_race')->after('playable_class_id');
        });

        Schema::dropIfExists('playable_races');
    }
};
