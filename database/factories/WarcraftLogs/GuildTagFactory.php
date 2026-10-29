<?php

namespace Database\Factories\WarcraftLogs;

use App\Models\WarcraftLogs\Guild;
use App\Models\WarcraftLogs\GuildTag;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends Factory<GuildTag>
 */
class GuildTagFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Model>
     */
    protected $model = GuildTag::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->word(),
            'count_attendance' => fake()->boolean(30),
        ];
    }

    /**
     * Indicate that the guild tag should count attendance.
     */
    public function countsAttendance(): static
    {
        return $this->state(fn (array $attributes) => [
            'count_attendance' => true,
        ]);
    }

    /**
     * Indicate that the guild tag should not count attendance.
     */
    public function doesNotCountAttendance(): static
    {
        return $this->state(fn (array $attributes) => [
            'count_attendance' => false,
        ]);
    }

    /**
     * Indicate that the guild tag belongs to a Warcraft Logs guild, a new one
     * when none is given.
     */
    public function forGuild(?Guild $guild = null): static
    {
        return $this->state(fn (array $attributes) => [
            'warcraft_logs_guild_id' => $guild?->id ?? Guild::factory(),
        ]);
    }
}
