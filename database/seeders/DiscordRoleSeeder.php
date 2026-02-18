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
            ['id' => '829021769448816691', 'name' => 'Officer', 'position' => 1, 'is_visible' => true],
            ['id' => '1467994755953852590', 'name' => 'Loot Councillor', 'position' => 2, 'is_visible' => true],
            ['id' => '1265247017215594496', 'name' => 'Raider', 'position' => 3, 'is_visible' => true],
            ['id' => '829022020301094922', 'name' => 'Member', 'position' => 4, 'is_visible' => true],
            ['id' => '829022292590985226', 'name' => 'Guest', 'position' => 5, 'is_visible' => true],
            ['id' => '1473267525927174316', 'name' => 'Daily Quest Subscriber', 'position' => 6, 'is_visible' => false],
        ];

        foreach ($roles as $role) {
            DiscordRole::updateOrCreate(
                ['id' => $role['id']],
                $role
            );
        }
    }
}
