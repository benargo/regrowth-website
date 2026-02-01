<?php

namespace Database\Factories\WarcraftLogs;

use App\Models\TBC\Phase;
use App\Models\WarcraftLogs\GuildTag;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\WarcraftLogs\GuildTag>
 */
class GuildTagFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
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
            'tbc_phase_id' => fake()->optional(0.5)->randomElement(
                Phase::pluck('id')->toArray() ?: [null]
            ),
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
     * Indicate that the guild tag should be associated with a phase.
     */
    public function withPhase(?Phase $phase = null): static
    {
        return $this->state(fn (array $attributes) => [
            'tbc_phase_id' => $phase?->id ?? Phase::factory(),
        ]);
    }

    /**
     * Indicate that the guild tag should not be associated with a phase.
     */
    public function withoutPhase(): static
    {
        return $this->state(fn (array $attributes) => [
            'tbc_phase_id' => null,
        ]);
    }
}
