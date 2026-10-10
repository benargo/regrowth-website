<?php

namespace Database\Seeders;

use App\Enums\Faction;
use App\Enums\Theme;
use App\Http\Integrations\Blizzard\BlizzardNamespace;
use App\Http\Integrations\WarcraftLogs\WarcraftLogsNamespace;
use App\Models\GameVersion;
use App\Models\WarcraftLogs\Guild;
use Carbon\Carbon;
use Carbon\CarbonTimeZone;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;

class GameVersionSeeder extends Seeder
{
    /**
     * The game versions to seed, each with the Warcraft Logs guild it uses.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function definitions(): array
    {
        return [
            [
                'title' => 'Mists of Pandaria Classic',
                'slug' => 'mop',
                'realm' => 'Mirage Raceway',
                'guild_name' => config('services.blizzard.guild.name'),
                'uses_surnames' => false,
                'faction' => Faction::ALLIANCE->value,
                'release_date' => Carbon::create(2021, 9, 4, 0, 0, 0, CarbonTimeZone::create('Europe/Paris')),
                'theme' => Theme::CLASSIC->value,
                'blizzard_namespace' => BlizzardNamespace::CLASSIC->value,
                'warcraft_logs_guild' => ['id' => 598032, 'namespace' => WarcraftLogsNamespace::Classic],
            ],
            [
                'title' => 'Burning Crusade Classic (Anniversary)',
                'slug' => 'anniversary',
                'realm' => 'Thunderstrike',
                'guild_name' => config('services.blizzard.guild.name'),
                'uses_surnames' => false,
                'faction' => Faction::ALLIANCE->value,
                'release_date' => Carbon::create(2024, 11, 22, 0, 0, 0, CarbonTimeZone::create('Europe/Paris')),
                'theme' => Theme::CLASSIC->value,
                'blizzard_namespace' => BlizzardNamespace::ANNIVERSARY->value,
                'warcraft_logs_guild' => ['id' => 774848, 'namespace' => WarcraftLogsNamespace::Anniversary],
            ],
            [
                'title' => 'World of Warcraft: Forever',
                'slug' => 'forever',
                'realm' => null,
                'guild_name' => config('services.blizzard.guild.name'),
                'uses_surnames' => false,
                'faction' => Faction::HORDE->value,
                'release_date' => Carbon::create(2026, 11, 5, 0, 0, 0, CarbonTimeZone::create('Europe/Paris')),
                'theme' => Theme::FOREVER->value,
                'blizzard_namespace' => null,
                'warcraft_logs_guild' => null,
            ],
        ];
    }

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach ($this->definitions() as $definition) {
            $guild = Arr::pull($definition, 'warcraft_logs_guild');

            if ($guild !== null) {
                Guild::firstOrCreate(['id' => $guild['id']], ['namespace' => $guild['namespace']]);
            }

            GameVersion::updateOrCreate(
                ['slug' => $definition['slug']],
                [...$definition, 'warcraft_logs_guild_id' => $guild['id'] ?? null],
            );
        }
    }
}
