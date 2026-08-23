<?php

namespace Database\Factories;

use App\Models\LootPriority;
use App\Models\PlayableClass;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends Factory<LootPriority>
 */
class LootPriorityFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Model>
     */
    protected $model = LootPriority::class;

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
            'playable_class_id' => null,
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

    /**
     * Attach the priority to a playable class.
     */
    public function withPlayableClass(?PlayableClass $playableClass = null): static
    {
        return $this->state(fn (array $attributes) => [
            'playable_class_id' => $playableClass ?? PlayableClass::factory(),
        ]);
    }
}
