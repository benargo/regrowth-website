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
        Schema::create('playable_specializations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('playable_class_id')->constrained('playable_classes')->cascadeOnDelete();
            $table->enum('role', ['Tank', 'Healer', 'DPS']);
            $table->string('name');
            $table->index(['playable_class_id', 'role', 'name']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('playable_specializations');
    }
};
