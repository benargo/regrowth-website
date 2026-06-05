<?php

namespace Database\Seeders;

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
            GuildRankSeeder::class,

            // Then seed the datasets
            PhaseSeeder::class,
            RaidSeeder::class,
            BossSeeder::class,
            ZoneSeeder::class,
            PlayableClassSeeder::class,
            PlayableRaceSeeder::class,
            TargetMarkerSeeder::class,

            // Then seed the daily quests data
            DailyQuestSeeder::class,

            // Then seed the loot bias data
            PrioritySeeder::class,
            ItemSeeder::class,
        ]);
    }
}
