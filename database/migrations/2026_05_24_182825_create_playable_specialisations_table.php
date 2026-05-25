<?php

use App\Enums\PlayableSpecRole;
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
            $table->enum('role', PlayableSpecRole::cases());
            $table->string('name');
            $table->timestamps();

            $table->index(['playable_class_id', 'role', 'name']);
        });

        Schema::create('pivot_character_specializations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('character_id')->constrained('characters')->cascadeOnDelete();
            $table->unsignedBigInteger('playable_specialization_id');
            $table->foreign('playable_specialization_id', 'fk_char_spec_specialization_id')->references('id')->on('playable_specializations')->cascadeOnDelete();
            $table->boolean('is_raid_spec')->default(false);
            $table->timestamps();

            $table->unique(['character_id', 'playable_specialization_id'], 'uq_char_spec');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pivot_character_specializations');
        Schema::dropIfExists('playable_specializations');
    }
};
