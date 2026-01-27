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
        Schema::create('tbc_raids', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->string('difficulty', 50);
            $table->foreignId('phase_id')->constrained('tbc_phases');
            $table->integer('max_players');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tbc_raids');
    }
};
