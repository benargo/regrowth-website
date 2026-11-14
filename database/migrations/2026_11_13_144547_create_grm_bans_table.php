<?php

use App\Models\Character;
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
        Schema::create('grm_bans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignIdFor(GameVersion::class)->constrained()->cascadeOnDelete();
            $table->string('grm_guid', 40);
            $table->string('name', 64);
            $table->foreignIdFor(Character::class)->nullable()->constrained()->nullOnDelete();
            $table->string('user_id')->nullable();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->string('banned_by', 64)->nullable();
            $table->timestamp('banned_at')->nullable();
            $table->text('reason')->nullable();
            $table->timestamp('unbanned_at')->nullable();
            $table->timestamps();

            $table->unique(['game_version_id', 'grm_guid']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('grm_bans');
    }
};
