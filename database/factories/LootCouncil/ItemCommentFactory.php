<?php

namespace Database\Factories\LootCouncil;

use App\Models\LootCouncil\Item;
use App\Models\LootCouncil\ItemComment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LootCouncil\ItemComment>
 */
class ItemCommentFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected $model = ItemComment::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'item_id' => Item::factory(),
            'user_id' => User::factory(),
            'body' => fake()->paragraph(),
        ];
    }

    /**
     * Set a specific comment body.
     */
    public function withBody(string $body): static
    {
        return $this->state(fn (array $attributes) => [
            'body' => $body,
        ]);
    }

    /**
     * Create a short comment.
     */
    public function short(): static
    {
        return $this->state(fn (array $attributes) => [
            'body' => fake()->sentence(),
        ]);
    }

    /**
     * Create a long/detailed comment.
     */
    public function detailed(): static
    {
        return $this->state(fn (array $attributes) => [
            'body' => fake()->paragraphs(3, true),
        ]);
    }
}
