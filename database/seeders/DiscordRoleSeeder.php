<?php

namespace Database\Seeders;

use App\Models\DiscordRole;
use Illuminate\Database\Seeder;

class DiscordRoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
            ['id' => '829021769448816691', 'name' => 'Officer', 'position' => 1],
            ['id' => '1467994755953852590', 'name' => 'Loot Councillor', 'position' => 2],
            ['id' => '1265247017215594496', 'name' => 'Raider', 'position' => 3],
            ['id' => '829022020301094922', 'name' => 'Member', 'position' => 4],
            ['id' => '829022292590985226', 'name' => 'Guest', 'position' => 5],
        ];

        foreach ($roles as $role) {
            DiscordRole::updateOrCreate(
                ['id' => $role['id']],
                $role
            );
        }
    }
}
