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
            ['sort_order' => 0, 'name' => 'Officer'],
            ['sort_order' => 1, 'name' => 'Officer'],
            ['sort_order' => 2, 'name' => 'Raider'],
            ['sort_order' => 3, 'name' => 'Trial Raider'],
            ['sort_order' => 4, 'name' => 'Warden'],
            ['sort_order' => 5, 'name' => 'Champion'],
            ['sort_order' => 6, 'name' => 'Veteran'],
            ['sort_order' => 7, 'name' => 'Member'],
            ['sort_order' => 8, 'name' => 'Initiate'],
            ['sort_order' => 9, 'name' => 'Inactive'],
        ];

        foreach ($ranks as $rank) {
            GuildRank::updateOrCreate(
                ['sort_order' => $rank['sort_order']],
                $rank
            );
        }
    }
}
