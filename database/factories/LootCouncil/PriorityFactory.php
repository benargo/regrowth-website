<?php

namespace Database\Factories\LootCouncil;

use App\Models\LootCouncil\Priority;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends Factory<Priority>
 */
class PriorityFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Model>
     */
    protected $model = Priority::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->unique()->numerify('Priority-###'),
            'type' => fake()->randomElement(['role', 'class', 'spec']),
        ];
    }

    /**
     * Indicate that the priority is a role type.
     */
    public function role(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'role',
        ]);
    }

    /**
     * Indicate that the priority is a class type.
     */
    public function classType(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'class',
        ]);
    }

    /**
     * Indicate that the priority is a spec type.
     */
    public function spec(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'spec',
        ]);
    }
}
