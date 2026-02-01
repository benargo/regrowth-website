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
        Schema::create('wcl_guild_tags', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('count_attendance')->default(false);
            $table->foreignId('tbc_phase_id')->nullable()->constrained('tbc_phases')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wcl_guild_tags');
    }
};
