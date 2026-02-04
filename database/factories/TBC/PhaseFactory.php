<?php

namespace Database\Factories\TBC;

use App\Models\TBC\Phase;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TBC\Phase>
 */
class PhaseFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected $model = Phase::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'number' => fake()->randomFloat(1, 1, 9),
            'description' => fake()->sentence(3),
            'start_date' => fake()->optional(0.7)->dateTimeBetween('-2 years', '+1 year'),
        ];
    }

    /**
     * Indicate that the phase has started.
     */
    public function started(): static
    {
        return $this->state(fn (array $attributes) => [
            'start_date' => fake()->dateTimeBetween('-2 years', 'now'),
        ]);
    }

    /**
     * Indicate that the phase is upcoming.
     */
    public function upcoming(): static
    {
        return $this->state(fn (array $attributes) => [
            'start_date' => fake()->dateTimeBetween('now', '+1 year'),
        ]);
    }

    /**
     * Indicate that the phase has no start date.
     */
    public function unscheduled(): static
    {
        return $this->state(fn (array $attributes) => [
            'start_date' => null,
        ]);
    }
}
