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
        Schema::create('discord_roles', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->integer('position'); // ->unique() removed — see 2026_03_01_133722_drop_index_from_position_column_on_discord_roles_table.php
            $table->boolean('can_comment_on_loot_items')->default(false);
            $table->timestamps();
        });

        Schema::create('discord_role_user', function (Blueprint $table) {
            $table->string('discord_role_id');
            $table->string('user_id');
            $table->primary(['discord_role_id', 'user_id']);
            $table->foreign('discord_role_id')->references('id')->on('discord_roles')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });

        // Initial role data removed — managed by DiscordRoleSeeder.

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('roles');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('roles')->nullable();
        });

        Schema::dropIfExists('discord_role_user');
        Schema::dropIfExists('discord_roles');
    }
};
