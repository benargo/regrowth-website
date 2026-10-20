<?php

namespace Database\Factories;

use App\Models\Boss;
use App\Models\Comment;
use App\Models\Item;
use App\Models\Raid;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

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
            'name' => Str::title(fake()->unique()->words(3, true)),
            'raid_id' => Raid::factory(),
            'notes' => fake()->optional()->paragraph(),
        ];
    }

    /**
     * Set the sort order after creation, since Boss auto-assigns sort_order on create.
     */
    public function order(int $order): static
    {
        return $this->afterCreating(function (Boss $boss) use ($order) {
            $boss->update(['sort_order' => $order]);
        });
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
