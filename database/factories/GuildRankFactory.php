<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\GuildRank>
 */
class GuildRankFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'position' => fake()->unique()->numberBetween(0, 9),
            'name' => fake()->word(),
            'count_attendance' => true,
        ];
    }

    /**
     * Indicate that the guild rank should not count attendance.
     */
    public function doesNotCountAttendance(): static
    {
        return $this->state(fn (array $attributes) => [
            'count_attendance' => false,
        ]);
    }
}
