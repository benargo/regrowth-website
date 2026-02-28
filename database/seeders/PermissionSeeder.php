<?php

namespace Database\Seeders;

use App\Models\DiscordRole;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = [
            'comment-on-loot-items',
            'view-attendance-dashboard',
        ];

        // Create permissions if they don't exist
        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // Ensure the Officer role has the appropriate permissions.
        DiscordRole::find('829021769448816691')?->givePermissionTo('view-attendance-dashboard');
    }
}
