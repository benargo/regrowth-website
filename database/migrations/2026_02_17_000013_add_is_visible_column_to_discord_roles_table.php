<?php

use App\Models\DiscordRole;
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

        // Set 'is_visible' to true for existing roles that should be visible
        $visibleRoleIds = [
            '829021769448816691', // Officer
            '1467994755953852590', // Loot Councillor
            '1265247017215594496', // Raider
            '829022020301094922', // Member
            '829022292590985226', // Guest
        ];

        DiscordRole::whereIn('id', $visibleRoleIds)->update(['is_visible' => true]);
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
