<?php

use App\Models\DiscordRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Create the pivot table for DiscordRole and Permission
        Schema::create('discord_role_has_permissions', function (Blueprint $table) {
            $table->string('discord_role_id');
            $table->unsignedBigInteger('permission_id');

            $table->foreign('discord_role_id')
                ->references('id')->on('discord_roles')
                ->onDelete('cascade');

            $table->foreign('permission_id')
                ->references('id')->on('permissions')
                ->onDelete('cascade');

            $table->primary(['discord_role_id', 'permission_id'], 'discord_role_permission_primary');
        });

        // Assign existing permissions to roles based on the old 'can_comment_on_loot_items' column
        DiscordRole::where('can_comment_on_loot_items', true)->each(function (DiscordRole $role) {
            $permission = Permission::firstOrCreate(['name' => 'comment-on-loot-items', 'guard_name' => 'web']);
            $role->givePermissionTo($permission);
        });

        // Remove the old column after migrating permissions
        Schema::table('discord_roles', function (Blueprint $table) {
            $table->dropColumn('can_comment_on_loot_items');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop the pivot table
        Schema::dropIfExists('discord_role_has_permissions');

        // Re-add the old column to the discord_roles table
        Schema::table('discord_roles', function (Blueprint $table) {
            $table->boolean('can_comment_on_loot_items')->default(false);
        });

        // Restore 'can_comment_on_loot_items' to officers.
        $officerRole = DiscordRole::find('829021769448816691'); // Officer role ID
        if ($officerRole) {
            $officerRole->update(['can_comment_on_loot_items' => true]);
        }
    }
};
