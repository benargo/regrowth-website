<?php

namespace Database\Seeders;

use Database\Seeders\TBC\BossSeeder;
use Database\Seeders\DailyQuestSeeder;
use Database\Seeders\TBC\PhaseSeeder;
use Database\Seeders\TBC\RaidSeeder;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            // Start with the seeders that handle authentication and authorization.
            DiscordRoleSeeder::class,
            SiteAdminSeeder::class,
            PermissionSeeder::class,

            // Then seed the core Blizzard data.
            DailyQuestSeeder::class,
            GuildRankSeeder::class,

            // Then seed the Warcraft Logs data.
            PhaseSeeder::class,
            RaidSeeder::class,
            BossSeeder::class,
            ZoneSeeder::class,

            // Then seed the loot bias data
            PrioritySeeder::class,
        ]);
    }
}
