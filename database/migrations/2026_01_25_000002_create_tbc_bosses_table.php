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
        Schema::create('tbc_bosses', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->foreignId('raid_id')->constrained('tbc_raids');
            $table->integer('encounter_order');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tbc_bosses');
    }
};
