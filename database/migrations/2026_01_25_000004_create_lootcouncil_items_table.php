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
        Schema::create('lootcouncil_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('raid_id')->constrained('tbc_raids');
            $table->foreignId('boss_id')->nullable()->constrained('tbc_bosses');
            $table->string('group', 100)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lootcouncil_items');
    }
};
