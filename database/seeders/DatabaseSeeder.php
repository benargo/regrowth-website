<?php

namespace Database\Seeders;

use Database\Seeders\LootCouncil\ItemsSeeder;
use Database\Seeders\LootCouncil\PrioritySeeder;
use Database\Seeders\TBC\BossSeeder;
use Database\Seeders\TBC\PhaseSeeder;
use Database\Seeders\TBC\RaidSeeder;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            PhaseSeeder::class,
            RaidSeeder::class,
            BossSeeder::class,
            PrioritySeeder::class,
            ItemsSeeder::class,
        ]);
    }
}
