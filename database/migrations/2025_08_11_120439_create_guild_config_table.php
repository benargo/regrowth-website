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
        Schema::create('guild_config', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('realm_slug');
            $table->string('region');
            $table->unsignedBigInteger('blizzard_guild_id')->nullable();
            $table->unsignedBigInteger('blizzard_realm_id')->nullable();
            $table->unsignedInteger('tmb_guild_id')->nullable();
            $table->unsignedBigInteger('warcraftlogs_guild_id')->unique()->nullable();
            $table->timestamps();

            /**
             * Index on region and realm_slug.
             * This allows for efficient querying of guilds by region and realm_slug.
             */
            $table->index(['region', 'realm_slug']);

            /** 
             * Unique constraint on guild identity.
             * This ensures that no two guilds can have the same name, realm_slug, and region combination.
             * The warcraftlogs_guild_id is unique on its own, but this
             * constraint allows for guilds that may not have a WarcraftLogs ID yet.
             */
            $table->unique(['name', 'realm_slug', 'region'], 'guild_identity_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('guild_config');
    }
};
