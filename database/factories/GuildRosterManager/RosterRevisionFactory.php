<?php

namespace Database\Factories\GuildRosterManager;

use App\Models\GameVersion;
use App\Models\GuildRosterManager\RosterRevision;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RosterRevision>
 */
class RosterRevisionFactory extends Factory
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
            'revision' => fake()->unique()->numberBetween(1, 1000000),
            'upload_id' => null,
            'committed_at' => now(),
            'ranks' => [],
            'ranks_fingerprint' => hash('sha256', '[]'),
        ];
    }
}
