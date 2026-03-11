<?php

namespace Database\Factories;

use App\Models\Character;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PlannedAbsence>
 */
class PlannedAbsenceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = fake()->dateTimeBetween('-1 month', '+1 month');
        $end = fake()->dateTimeBetween($start, (clone $start)->modify('+2 weeks'));

        return [
            'character_id' => null,
            'start_date' => $start,
            'end_date' => $end,
            'reason' => fake()->paragraph(),
            'created_by' => User::factory(),
        ];
    }

    public function withCharacter(): static
    {
        return $this->state(fn (array $attributes) => [
            'character_id' => Character::factory(),
        ]);
    }

    public function withoutEndDate(): static
    {
        return $this->state(fn (array $attributes) => [
            'end_date' => null,
        ]);
    }
}
