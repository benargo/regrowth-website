<?php

use App\Enums\CharacterRole;
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
        Schema::create('character_specialisations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('playable_class_id')->constrained('playable_classes')->cascadeOnDelete();
            $table->enum('role', CharacterRole::cases());
            $table->string('name');
            $table->timestamps();

            $table->index(['playable_class_id', 'role', 'name']);
        });

        Schema::table('characters', function (Blueprint $table) {
            $table->foreignId('specialisation_id')
                ->after('playable_class_id')
                ->nullable()
                ->constrained('character_specialisations')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('characters', function (Blueprint $table) {
            $table->dropConstrainedForeignId('specialisation_id');
        });

        Schema::dropIfExists('character_specialisations');
    }
};
