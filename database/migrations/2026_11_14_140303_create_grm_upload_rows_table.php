<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the table of per-row outcomes for GRM uploads.
     */
    public function up(): void
    {
        Schema::create('grm_upload_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('upload_id')->constrained('grm_uploads')->cascadeOnDelete();
            $table->unsignedBigInteger('character_id')->nullable();
            $table->string('guid', 40);
            $table->string('name', 64);
            $table->string('status', 16);
            $table->string('reason', 32);
            $table->text('message')->nullable();
            $table->boolean('is_latest')->default(false);
            $table->timestamps();

            $table->foreign('character_id')->references('id')->on('characters')->nullOnDelete();
            $table->index(['upload_id', 'reason']);
            $table->index(['guid', 'id']);
            $table->index(['is_latest', 'status']);
        });
    }

    /**
     * Drop the per-row outcomes table.
     */
    public function down(): void
    {
        Schema::dropIfExists('grm_upload_rows');
    }
};
