<?php

namespace Database\Seeders;

use App\Models\DiscordRole;
use App\Models\Permission;
use App\Models\PermissionGroup;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;

class PermissionSeeder extends Seeder
{
    /**
     * The Officer role, which receives every permission on a database where no role holds any.
     */
    private const string OFFICER_ROLE_ID = '829021769448816691';

    /**
     * The permissions to seed.
     *
     * @var array<int, array{name: string, guard_name: string, label: string, description: string}>
     */
    private array $permissions = [
        /**
         * Loot bias tool permissions
         */
        ['name' => 'comment-on-loot-items', 'guard_name' => 'web', 'label' => 'Comment on loot items', 'description' => 'Post comments on an item\'s page.'],
        ['name' => 'delete-any-comment', 'guard_name' => 'web', 'label' => 'Delete any comment', 'description' => 'Delete comments written by anyone.'],
        ['name' => 'edit-any-comment', 'guard_name' => 'web', 'label' => 'Edit any comment', 'description' => 'Edit comments written by anyone.'],
        ['name' => 'edit-items', 'guard_name' => 'web', 'label' => 'Edit items', 'description' => 'Change an item\'s loot priorities and notes.'],
        ['name' => 'mark-comment-as-resolved', 'guard_name' => 'web', 'label' => 'Resolve comments', 'description' => 'Mark comments on loot items as resolved.'],
        ['name' => 'react-to-comments', 'guard_name' => 'web', 'label' => 'React to comments', 'description' => 'Add emoji reactions to comments.'],
        ['name' => 'view-priorities-page', 'guard_name' => 'web', 'label' => 'View loot priorities', 'description' => 'See the loot priorities page.'],

        /**
         * Raid management permissions
         */
        ['name' => 'manage-reports', 'guard_name' => 'web', 'label' => 'Manage reports', 'description' => 'Add, link and edit raid reports.'],
        ['name' => 'view-attendance', 'guard_name' => 'web', 'label' => 'View attendance', 'description' => 'See everyone\'s raid attendance.'],
        ['name' => 'view-old-raid-plans', 'guard_name' => 'web', 'label' => 'View old raid plans', 'description' => 'Look back at plans for past raids.'],
        ['name' => 'manage-raid-plans', 'guard_name' => 'web', 'label' => 'Manage raid plans', 'description' => 'Create and edit plans for upcoming raids.'],
        ['name' => 'view-handbook', 'guard_name' => 'web', 'label' => 'View the handbook', 'description' => 'Read the raider handbook.'],
        ['name' => 'manage-handbook', 'guard_name' => 'web', 'label' => 'Manage the handbook', 'description' => 'Write, edit, reorder and delete handbook chapters.'],

        /**
         * Planned absences permissions
         */
        ['name' => 'create-planned-absences', 'guard_name' => 'web', 'label' => 'Add planned absences', 'description' => 'Record a planned absence for any raider.'],
        ['name' => 'view-planned-absences', 'guard_name' => 'web', 'label' => 'View planned absences', 'description' => 'See everyone\'s planned absences.'],
        ['name' => 'update-planned-absences', 'guard_name' => 'web', 'label' => 'Edit planned absences', 'description' => 'Change anyone\'s planned absences.'],
        ['name' => 'delete-planned-absences', 'guard_name' => 'web', 'label' => 'Delete planned absences', 'description' => 'Remove anyone\'s planned absences.'],
        ['name' => 'backdate-planned-absences', 'guard_name' => 'web', 'label' => 'Backdate planned absences', 'description' => 'Record planned absences that start in the past.'],

        /**
         * Character management permissions
         */
        ['name' => 'create-characters', 'guard_name' => 'web', 'label' => 'Add characters', 'description' => 'Add characters to the roster by hand.'],
        ['name' => 'update-characters', 'guard_name' => 'web', 'label' => 'Edit characters', 'description' => 'Change a character\'s details.'],
        ['name' => 'delete-characters', 'guard_name' => 'web', 'label' => 'Delete characters', 'description' => 'Remove characters from the roster.'],

        /**
         * Warcraft Logs permissions
         */
        ['name' => 'view-warcraft-logs-guilds', 'guard_name' => 'web', 'label' => 'View Warcraft Logs guilds', 'description' => 'See the guilds whose reports the site imports.'],
        ['name' => 'create-warcraft-logs-guilds', 'guard_name' => 'web', 'label' => 'Add Warcraft Logs guilds', 'description' => 'Start importing reports from another guild.'],
        ['name' => 'update-warcraft-logs-guilds', 'guard_name' => 'web', 'label' => 'Edit Warcraft Logs guilds', 'description' => 'Change a Warcraft Logs guild\'s settings.'],
        ['name' => 'delete-warcraft-logs-guilds', 'guard_name' => 'web', 'label' => 'Delete Warcraft Logs guilds', 'description' => 'Stop importing a guild\'s reports.'],
        ['name' => 'update-warcraft-logs-tags', 'guard_name' => 'web', 'label' => 'Edit Warcraft Logs tags', 'description' => 'Choose which report tags count towards attendance.'],

        /**
         * Other permissions
         */
        ['name' => 'edit-datasets', 'guard_name' => 'web', 'label' => 'Edit datasets', 'description' => 'Edit game data such as phases, bosses and raids.'],
        ['name' => 'impersonate-roles', 'guard_name' => 'web', 'label' => 'View as another role', 'description' => 'Has no effect yet: only admins can view the site as another role.'],
        ['name' => 'view-officer-dashboard', 'guard_name' => 'web', 'label' => 'View the officers\' dashboard', 'description' => 'Open the officers\' dashboard.'],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach ($this->permissions as $entry) {
            $permission = Permission::where('name', $entry['name'])
                ->where('guard_name', $entry['guard_name'])
                ->first();

            if ($permission === null) {
                Permission::create(Arr::only($entry, ['name', 'guard_name', 'label', 'description']));

                continue;
            }

            if ($permission->label === null) {
                $permission->update(Arr::only($entry, ['label', 'description']));
            }
        }

        Permission::whereNotIn('name', Arr::pluck($this->permissions, 'name'))->where('guard_name', 'web')->delete();

        if (! DiscordRole::has('permissions')->exists()) {
            DiscordRole::find(self::OFFICER_ROLE_ID)?->permissions()->sync(Permission::where('guard_name', 'web')->pluck('id'));
        }

        if (Permission::whereNotNull('permission_group_id')->exists()) {
            return;
        }

        foreach ($this->groups() as $name => $permissionNames) {
            $group = PermissionGroup::firstOrCreate(['name' => $name]);

            Permission::whereIn('name', $permissionNames)
                ->where('guard_name', 'web')
                ->get()
                ->each->update(['permission_group_id' => $group->id]);
        }
    }

    /**
     * The starting groups, mapped to the names of their permissions.
     *
     * @return array<string, list<string>>
     */
    private function groups(): array
    {
        return [
            'Loot Bias Tool' => [
                'comment-on-loot-items',
                'delete-any-comment',
                'edit-any-comment',
                'edit-items',
                'mark-comment-as-resolved',
                'react-to-comments',
                'view-priorities-page',
            ],
            'Raid Management' => [
                'manage-reports',
                'view-attendance',
                'view-old-raid-plans',
                'manage-raid-plans',
                'view-handbook',
                'manage-handbook',
            ],
            'Planned Absences' => [
                'create-planned-absences',
                'view-planned-absences',
                'update-planned-absences',
                'delete-planned-absences',
                'backdate-planned-absences',
            ],
            'Character Management' => [
                'create-characters',
                'update-characters',
                'delete-characters',
            ],
            'Warcraft Logs' => [
                'view-warcraft-logs-guilds',
                'create-warcraft-logs-guilds',
                'update-warcraft-logs-guilds',
                'delete-warcraft-logs-guilds',
                'update-warcraft-logs-tags',
            ],
        ];
    }
}
