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
        Schema::create('grm_roster_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(GameVersion::class)->constrained()->cascadeOnDelete();
            $table->unsignedInteger('revision');
            $table->foreignUuid('upload_id')->nullable()->constrained('grm_uploads')->nullOnDelete();
            $table->timestamp('committed_at');
            $table->json('ranks');
            $table->char('ranks_fingerprint', 64);
            $table->timestamps();

            $table->unique(['game_version_id', 'revision']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('grm_roster_revisions');
    }
};
