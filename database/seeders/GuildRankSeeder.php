<?php

namespace Database\Seeders;

use App\Models\GuildRank;
use Illuminate\Database\Seeder;

class GuildRankSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $ranks = [
            ['position' => 0, 'name' => 'Guild Master'],
            ['position' => 1, 'name' => 'Officer'],
            ['position' => 2, 'name' => 'Raider'],
            ['position' => 3, 'name' => 'Trial Raider'],
            ['position' => 4, 'name' => 'Warden'],
            ['position' => 5, 'name' => 'Champion'],
            ['position' => 6, 'name' => 'Veteran'],
            ['position' => 7, 'name' => 'Member'],
            ['position' => 8, 'name' => 'Initiate'],
            ['position' => 9, 'name' => 'Inactive'],
        ];

        foreach ($ranks as $rank) {
            GuildRank::updateOrCreate(
                ['position' => $rank['position']],
                $rank
            );
        }
    }
}
