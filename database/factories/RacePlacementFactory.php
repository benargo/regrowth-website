<?php

namespace Database\Factories;

use App\Models\Character;
use App\Models\GameVersion;
use App\Models\RacePlacement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RacePlacement>
 */
class RacePlacementFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'game_version_id' => GameVersion::factory(),
            'character_id' => Character::factory(),
            'position' => 1,
            'reached_at' => now(),
        ];
    }
}
