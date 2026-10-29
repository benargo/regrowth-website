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
        Schema::create('discord_channels', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name')->nullable();
            $table->unsignedTinyInteger('type')->nullable();
            $table->integer('position')->default(0);
            $table->string('parent_id')->nullable()->index();
            $table->text('topic')->nullable();
            $table->boolean('nsfw')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('discord_channels');
    }
};
