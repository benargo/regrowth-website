<?php

namespace Database\Factories;

use App\Enums\Faction;
use App\Enums\Theme;
use App\Http\Integrations\Blizzard\BlizzardNamespace;
use App\Http\Integrations\WarcraftLogs\WarcraftLogsNamespace;
use App\Models\GameVersion;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

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
            'slug' => fake()->unique()->lexify('version-????'),
            'realm' => fake()->city(),
            'guild_name' => fake()->unique()->lexify('Guild ??????'),
            'faction' => fake()->randomElement(Faction::cases()),
            'release_date' => fake()->dateTimeBetween('-1 year', '+1 year'),
            'theme' => fake()->randomElement(Theme::cases()),
            'blizzard_namespace' => fake()->randomElement(BlizzardNamespace::cases()),
            'warcraftlogs_guild' => fake()->numberBetween(1, 999999),
            'warcraftlogs_namespace' => fake()->randomElement(WarcraftLogsNamespace::cases()),
        ];
    }

    /**
     * Indicate that this is the Burning Crusade Classic (Anniversary) version
     * that the item and daily quest seeders resolve by slug.
     */
    public function tbc(): static
    {
        return $this->state(fn (array $attributes) => [
            'slug' => 'tbc',
            'blizzard_namespace' => BlizzardNamespace::ANNIVERSARY,
        ]);
    }

    /**
     * Indicate that Blizzard can return this version's guild roster.
     */
    public function fetchableRoster(): static
    {
        return $this->state(fn (array $attributes) => [
            'realm' => 'Thunderstrike',
            'blizzard_namespace' => BlizzardNamespace::ANNIVERSARY,
            'release_date' => Carbon::now()->subMonth(),
        ]);
    }
}
