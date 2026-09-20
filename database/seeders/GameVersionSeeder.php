<?php

namespace Database\Seeders;

use App\Enums\Faction;
use App\Enums\Theme;
use App\Http\Integrations\Blizzard\BlizzardNamespace;
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
                'realm' => 'Thunderstrike',
                'faction' => Faction::ALLIANCE->value,
                'release_date' => Carbon::create(2026, 2, 6, 0, 0, 0, CarbonTimeZone::create('Europe/Paris')),
                'theme' => Theme::CLASSIC->value,
                'blizzard_namespace' => BlizzardNamespace::ANNIVERSARY->value,
                'warcraftlogs_guild' => 774848,
                'warcraftlogs_expansion' => 1001,
            ],
        ];
    }

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach ($this->definitions() as $game_version) {
            GameVersion::updateOrCreate(['title' => $game_version['title']], $game_version);
        }
    }
}
