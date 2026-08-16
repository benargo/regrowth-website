<?php

namespace Database\Factories;

use App\Models\Comment;
use App\Models\Item;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends Factory<Comment>
 */
class CommentFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Model>
     */
    protected $model = Comment::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'commentable_id' => Item::factory(),
            'commentable_type' => Item::class,
            'user_id' => User::factory(),
            'body' => fake()->paragraph(),
            'is_resolved' => false,
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

    public function resolved(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_resolved' => true,
        ]);
    }

    /**
     * Make the comment a reply to the given comment, inheriting its commentable.
     *
     * `parent_id` is not mass-assignable — that is what caps thread depth — so
     * it is assigned directly on the instance rather than through the state
     * array, which the fillable guard would drop.
     */
    public function replyTo(Comment $parent): static
    {
        return $this
            ->state(fn (array $attributes) => [
                'commentable_id' => $parent->commentable_id,
                'commentable_type' => $parent->commentable_type,
            ])
            ->afterMaking(function (Comment $comment) use ($parent): void {
                $comment->parent_id = $parent->id;
            });
    }
}
