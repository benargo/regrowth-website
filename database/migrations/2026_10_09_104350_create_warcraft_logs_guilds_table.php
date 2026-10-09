<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations. The ID is the Warcraft Logs guild ID, so it is
     * never auto-incremented.
     */
    public function up(): void
    {
        Schema::create('warcraft_logs_guilds', function (Blueprint $table): void {
            $table->unsignedBigInteger('id')->primary();
            $table->string('namespace');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('warcraft_logs_guilds');
    }
};
