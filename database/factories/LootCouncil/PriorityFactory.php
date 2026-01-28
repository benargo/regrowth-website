<?php

namespace Database\Factories\LootCouncil;

use App\Models\LootCouncil\Priority;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LootCouncil\Priority>
 */
class PriorityFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
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
            'title' => fake()->randomElement(['Tank', 'Healer', 'Melee DPS', 'Ranged DPS', 'Caster', 'Physical']),
            'type' => fake()->randomElement(['role', 'class', 'spec']),
            'media' => [
                'media_type' => 'spell',
                'media_id' => fake()->numberBetween(1000, 9999),
            ],
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
