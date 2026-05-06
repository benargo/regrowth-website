<?php

namespace Database\Seeders;

use App\Models\DiscordRole;
use App\Models\Permission;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;

class PermissionSeeder extends Seeder
{
    /**
     * The permissions to seed, organised by name.
     *
     * The 'group' field is used for categorisation in the dashboard and should be in snake_case. It is optional, but recommended for better organisation.
     */
    private array $permissions = [
        /**
         * Loot bias tool permissions
         */
        ['name' => 'comment-on-loot-items', 'group' => 'loot-bias-tool', 'guard_name' => 'web'],
        ['name' => 'delete-any-comment', 'group' => 'loot-bias-tool', 'guard_name' => 'web'],
        ['name' => 'edit-any-comment', 'group' => 'loot-bias-tool', 'guard_name' => 'web'],
        ['name' => 'edit-items', 'group' => 'loot-bias-tool', 'guard_name' => 'web'],
        ['name' => 'mark-comment-as-resolved', 'group' => 'loot-bias-tool', 'guard_name' => 'web'],
        ['name' => 'react-to-comments', 'group' => 'loot-bias-tool', 'guard_name' => 'web'],
        ['name' => 'view-loot-bias-tool', 'group' => 'loot-bias-tool', 'guard_name' => 'web'],
        ['name' => 'view-all-comments', 'group' => 'loot-bias-tool', 'guard_name' => 'web'],

        /**
         * Raid management permissions
         */
        ['name' => 'manage-reports', 'group' => 'raid-management', 'guard_name' => 'web'],
        ['name' => 'view-attendance', 'group' => 'raid-management', 'guard_name' => 'web'],
        ['name' => 'view-raid-plans', 'group' => 'raid-management', 'guard_name' => 'web'],
        ['name' => 'manage-raid-plans', 'group' => 'raid-management', 'guard_name' => 'web'],

        /**
         * Planned absences permissions
         */
        ['name' => 'create-planned-absences', 'group' => 'planned-absences', 'guard_name' => 'web'],
        ['name' => 'view-planned-absences', 'group' => 'planned-absences', 'guard_name' => 'web'],
        ['name' => 'update-planned-absences', 'group' => 'planned-absences', 'guard_name' => 'web'],
        ['name' => 'delete-planned-absences', 'group' => 'planned-absences', 'guard_name' => 'web'],
        ['name' => 'manage-planned-absences', 'group' => 'planned-absences', 'guard_name' => 'web'],

        /**
         * Daily quests permissions
         */
        ['name' => 'view-daily-quests', 'group' => 'daily-quests', 'guard_name' => 'web'],
        ['name' => 'set-daily-quests', 'group' => 'daily-quests', 'guard_name' => 'web'],
        ['name' => 'audit-daily-quests', 'group' => 'daily-quests', 'guard_name' => 'web'],

        /**
         * Hidden permissions (not shown in the dashboard, but still used for access control)
         */
        ['name' => 'edit-datasets', 'guard_name' => 'web'],
        ['name' => 'impersonate-roles', 'guard_name' => 'web'],
        ['name' => 'view-officer-dashboard', 'guard_name' => 'web'],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $officerRole = DiscordRole::find('829021769448816691');

        // Create permissions if they don't exist
        foreach ($this->permissions as $permission) {
            Permission::updateOrCreate(
                ['name' => $permission['name'], 'guard_name' => $permission['guard_name']],
                $permission
            );
            $officerRole?->givePermissionTo($permission['name']);
        }

        // Delete any permissions that are no longer in the list
        Permission::whereNotIn('name', Arr::pluck($this->permissions, 'name'))->where('guard_name', 'web')->delete();
    }
}
