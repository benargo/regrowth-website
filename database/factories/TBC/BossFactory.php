<?php

namespace Database\Factories\TBC;

use App\Models\LootCouncil\Comment;
use App\Models\LootCouncil\Item;
use App\Models\TBC\Boss;
use App\Models\TBC\Raid;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends Factory<Boss>
 */
class BossFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Model>
     */
    protected $model = Boss::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['Attumen the Huntsman', 'Moroes', 'Maiden of Virtue', 'Opera Event', 'The Curator', 'Shade of Aran', 'Netherspite', 'Prince Malchezaar', 'Illidan Stormrage', 'Kil\'jaeden']),
            'raid_id' => Raid::factory(),
            'encounter_order' => fake()->numberBetween(1, 12),
        ];
    }

    /**
     * Set the encounter order.
     */
    public function order(int $order): static
    {
        return $this->state(fn (array $attributes) => [
            'encounter_order' => $order,
        ]);
    }

    /**
     * Create the boss with items attached.
     */
    public function withItems(int $count = 3): static
    {
        return $this->has(Item::factory()->count($count), 'items');
    }

    /**
     * Create the boss with items and comments attached.
     */
    public function withComments(int $count = 3): static
    {
        return $this->has(
            Item::factory()->has(Comment::factory()->count($count), 'comments'),
            'items'
        );
    }
}
