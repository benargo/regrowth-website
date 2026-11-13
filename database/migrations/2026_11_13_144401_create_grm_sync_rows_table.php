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
        Schema::create('grm_sync_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('upload_id')->constrained('grm_uploads')->cascadeOnDelete();
            $table->string('kind', 16);
            $table->string('key', 40);
            $table->json('payload');
            $table->unsignedInteger('epoch')->default(0);
            $table->timestamps();

            $table->unique(['upload_id', 'kind', 'key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('grm_sync_rows');
    }
};
