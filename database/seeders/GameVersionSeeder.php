<?php

namespace Database\Seeders;

use App\Enums\Faction;
use App\Enums\Theme;
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
                'title' => 'Burning Crusade Classic (Anniversary)',
                'slug' => 'tbc',
                'realm' => 'Thunderstrike',
                'guild_name' => config('services.blizzard.guild.name'),
                'faction' => Faction::ALLIANCE->value,
                'release_date' => Carbon::create(2026, 2, 6, 0, 0, 0, CarbonTimeZone::create('Europe/Paris')),
                'theme' => Theme::CLASSIC->value,
                'blizzard_namespace' => BlizzardNamespace::ANNIVERSARY->value,
                'warcraftlogs_guild' => 774848,
                'warcraftlogs_namespace' => WarcraftLogsNamespace::Anniversary->value,
            ],
            [
                'title' => 'World of Warcraft: Forever',
                'slug' => 'forever',
                'realm' => null,
                'guild_name' => config('services.blizzard.guild.name'),
                'faction' => Faction::HORDE->value,
                'release_date' => Carbon::create(2026, 11, 5, 0, 0, 0, CarbonTimeZone::create('Europe/Paris')),
                'theme' => Theme::FOREVER->value,
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
