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
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $commentPermissionRoles = ['Officer', 'Loot Councillor', 'Raider'];

        $roles = DiscordRole::whereIn('name', $commentPermissionRoles)->get();

        foreach ($roles as $role) {
            $role->givePermissionTo('comment-on-loot-items');
        }
    }
}
