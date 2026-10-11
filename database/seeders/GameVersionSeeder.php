<?php

namespace Database\Seeders;

use App\Enums\Faction;
use App\Http\Integrations\Blizzard\BlizzardNamespace;
use App\Http\Integrations\WarcraftLogs\WarcraftLogsNamespace;
use App\Models\GameVersion;
use Carbon\Carbon;
use Carbon\CarbonTimeZone;
use Illuminate\Database\Seeder;

class GameVersionSeeder extends Seeder
{
    /** @var array<array<string, mixed>> */
    protected function definitions(): array
    {
        return [
            [
                'title' => 'Mists of Pandaria Classic',
                'slug' => 'mop',
                'realm' => 'Mirage Raceway',
                'guild_name' => config('services.blizzard.guild.name'),
                'faction' => Faction::ALLIANCE->value,
                'release_date' => Carbon::create(2021, 9, 4, 0, 0, 0, CarbonTimeZone::create('Europe/Paris')),
                'color' => 0x00FF98, // 0, 255, 152
                'blizzard_namespace' => BlizzardNamespace::CLASSIC->value,
                'warcraftlogs_guild' => 598032,
                'warcraftlogs_namespace' => WarcraftLogsNamespace::CLASSIC->value,
            ],
            [
                'title' => 'Burning Crusade Classic (Anniversary)',
                'slug' => 'tbc',
                'realm' => 'Thunderstrike',
                'guild_name' => config('services.blizzard.guild.name'),
                'faction' => Faction::ALLIANCE->value,
                'release_date' => Carbon::create(2026, 2, 6, 0, 0, 0, CarbonTimeZone::create('Europe/Paris')),
                'color' => 0x4AE832, // 74, 232, 50
                'blizzard_namespace' => BlizzardNamespace::ANNIVERSARY->value,
                'warcraftlogs_guild' => 774848,
                'warcraftlogs_namespace' => WarcraftLogsNamespace::ANNIVERSARY->value,
            ],
            [
                'title' => 'World of Warcraft: Forever',
                'slug' => 'forever',
                'realm' => null,
                'guild_name' => config('services.blizzard.guild.name'),
                'faction' => Faction::HORDE->value,
                'release_date' => Carbon::create(2026, 11, 5, 0, 0, 0, CarbonTimeZone::create('Europe/Paris')),
                'color' => 0xC69B3A, // 198, 155, 58
                'blizzard_namespace' => null,
                'warcraftlogs_guild' => null,
                'warcraftlogs_namespace' => null,
            ],
        ];
    }

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach ($this->definitions() as $game_version) {
            GameVersion::updateOrCreate(['slug' => $game_version['slug']], $game_version);
        }
    }
}
