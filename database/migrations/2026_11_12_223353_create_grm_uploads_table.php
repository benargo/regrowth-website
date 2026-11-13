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
        Schema::create('grm_uploads', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignIdFor(GameVersion::class)->constrained();
            $table->string('user_id');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->string('source', 16);
            $table->uuid('desktop_client_id')->nullable();
            $table->foreign('desktop_client_id')->references('id')->on('desktop_clients')->nullOnDelete();
            $table->char('idempotency_key', 64)->nullable();
            $table->string('outcome', 16);
            $table->unsignedInteger('base_revision')->nullable();
            $table->unsignedInteger('revision')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('ticket_expires_at')->nullable();
            $table->string('addon_version', 32)->nullable();
            $table->timestamp('captured_at');
            $table->unsignedInteger('member_count');
            $table->string('storage_path')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['game_version_id', 'captured_at']);
            $table->index(['game_version_id', 'idempotency_key']);
            $table->index(['user_id', 'game_version_id', 'outcome']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('grm_uploads');
    }
};
