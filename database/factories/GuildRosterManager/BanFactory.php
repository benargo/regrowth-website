<?php

namespace Database\Factories\GuildRosterManager;

use App\Models\Character;
use App\Models\GameVersion;
use App\Models\GuildRosterManager\Ban;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ban>
 */
class BanFactory extends Factory
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
            'grm_guid' => 'Player-'.fake()->unique()->numberBetween(1000, 9999999),
            'name' => fake()->firstName(),
            'banned_by' => fake()->firstName(),
            'banned_at' => now()->subDays(fake()->numberBetween(1, 90)),
            'reason' => fake()->sentence(),
        ];
    }

    /**
     * Indicate that the ban has been lifted.
     */
    public function unbanned(): static
    {
        return $this->state(fn (array $attributes) => [
            'unbanned_at' => now(),
        ]);
    }

    /**
     * Indicate that the ban was matched to the given character and its user.
     */
    public function forCharacter(Character $character): static
    {
        return $this->state(fn (array $attributes) => [
            'character_id' => $character->id,
            'user_id' => $character->user_id,
        ]);
    }
}
