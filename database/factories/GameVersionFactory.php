<?php

namespace Database\Factories;

use App\Enums\Faction;
use App\Enums\Theme;
use App\Http\Integrations\Blizzard\BlizzardNamespace;
use App\Http\Integrations\WarcraftLogs\WarcraftLogsNamespace;
use App\Models\GameVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GameVersion>
 */
class GameVersionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->words(3, true),
            'realm' => fake()->city(),
            'faction' => fake()->randomElement(Faction::cases()),
            'release_date' => fake()->dateTimeBetween('-1 year', '+1 year'),
            'theme' => fake()->randomElement(Theme::cases()),
            'blizzard_namespace' => fake()->randomElement(BlizzardNamespace::cases()),
            'warcraftlogs_guild' => fake()->numberBetween(1, 999999),
            'warcraftlogs_namespace' => fake()->randomElement(WarcraftLogsNamespace::cases()),
        ];
    }
}
