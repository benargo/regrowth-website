<?php

namespace Database\Seeders;

use App\Models\DiscordRole;
use Illuminate\Database\Seeder;

class DiscordRoleSeeder extends Seeder
{
    /**
     * Seed the guild roles, leaving any existing role rows untouched.
     */
    public function run(): void
    {
        $roles = [
            ['id' => '829021769448816691', 'name' => 'Officer', 'position' => 6, 'is_visible' => true, 'grants_permissions' => true, 'primary_color' => 'aa3b3b'],
            ['id' => '1467994755953852590', 'name' => 'Loot Councillor', 'position' => 5, 'is_visible' => true, 'grants_permissions' => true, 'primary_color' => 'a335ee'],
            ['id' => '1265247017215594496', 'name' => 'Raider', 'position' => 4, 'is_visible' => true, 'grants_permissions' => true, 'primary_color' => 'e67e22'],
            ['id' => '829022292590985226', 'name' => 'Guest', 'position' => 2, 'is_visible' => true, 'grants_permissions' => false, 'primary_color' => '9b59b6'],
        ];

        foreach ($roles as $role) {
            DiscordRole::firstOrCreate(['id' => $role['id']], $role);
        }
    }
}
