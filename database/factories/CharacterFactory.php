<?php

namespace Database\Factories;

use App\Models\GuildRank;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Character>
 */
class CharacterFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'rank_id' => null,
            'is_main' => false,
        ];
    }

    /**
     * Indicate that the character is a main character.
     */
    public function main(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_main' => true,
        ]);
    }

    /**
     * Indicate that the character is a loot councillor.
     */
    public function lootCouncillor(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_loot_councillor' => true,
        ]);
    }

    /**
     * Indicate that the character has a guild rank.
     */
    public function withRank(): static
    {
        return $this->state(fn (array $attributes) => [
            'rank_id' => GuildRank::factory(),
        ]);
    }

    /**
     * Indicate that the character has reached the level cap.
     */
    public function reachedLevelCap(): static
    {
        return $this->state(fn (array $attributes) => [
            'reached_level_cap_at' => now(),
        ]);
    }
}
