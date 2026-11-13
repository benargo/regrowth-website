<?php

use App\Models\GameVersion;
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
        Schema::create('grm_roster_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(GameVersion::class)->constrained()->cascadeOnDelete();
            $table->string('kind', 16);
            $table->string('key', 40);
            $table->json('payload');
            $table->char('fingerprint', 64);
            $table->unsignedInteger('epoch')->default(0);
            $table->unsignedInteger('revision');
            $table->timestamps();

            $table->unique(['game_version_id', 'kind', 'key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('grm_roster_entries');
    }
};
