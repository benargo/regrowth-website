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
        Schema::create('planned_absences', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('character_id')->constrained('characters')->cascadeOnDelete();
            $table->string('user_id')->nullable();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->datetime('start_date');
            $table->datetime('end_date')->nullable();
            $table->text('reason');
            $table->string('discord_message_id')->nullable(); // Nullable initially
            $table->string('created_by');
            $table->foreign('created_by')->references('id')->on('users')->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('planned_absences');
    }
};
