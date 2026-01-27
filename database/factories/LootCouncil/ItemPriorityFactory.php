<?php

namespace Database\Factories\LootCouncil;

use App\Models\LootCouncil\Item;
use App\Models\LootCouncil\ItemPriority;
use App\Models\LootCouncil\Priority;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LootCouncil\ItemPriority>
 */
class ItemPriorityFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected $model = ItemPriority::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'item_id' => Item::factory(),
            'priority_id' => Priority::factory(),
            'weight' => fake()->numberBetween(1, 100),
        ];
    }

    /**
     * Set a specific weight value.
     */
    public function weight(int $weight): static
    {
        return $this->state(fn (array $attributes) => [
            'weight' => $weight,
        ]);
    }

    /**
     * Indicate a high priority weight.
     */
    public function highPriority(): static
    {
        return $this->state(fn (array $attributes) => [
            'weight' => fake()->numberBetween(80, 100),
        ]);
    }

    /**
     * Indicate a low priority weight.
     */
    public function lowPriority(): static
    {
        return $this->state(fn (array $attributes) => [
            'weight' => fake()->numberBetween(1, 20),
        ]);
    }
}
