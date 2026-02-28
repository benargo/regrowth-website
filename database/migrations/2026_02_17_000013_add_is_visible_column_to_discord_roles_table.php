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
        Schema::table('discord_roles', function (Blueprint $table) {
            $table->boolean('is_visible')->default(false)->after('position');
        });

        // is_visible values managed by DiscordRoleSeeder.
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('discord_roles', 'is_visible')) {
            Schema::table('discord_roles', function (Blueprint $table) {
                $table->dropColumn('is_visible');
            });
        }
    }
};
