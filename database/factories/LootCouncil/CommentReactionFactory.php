<?php

namespace Database\Factories\LootCouncil;

use App\Models\LootCouncil\Comment;
use App\Models\LootCouncil\CommentReaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LootCouncil\CommentReaction>
 */
class CommentReactionFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected $model = CommentReaction::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'comment_id' => Comment::factory(),
            'user_id' => User::factory(),
        ];
    }

    /**
     * Create a reaction for a specific comment.
     */
    public function forComment(Comment $comment): static
    {
        return $this->state(fn (array $attributes) => [
            'comment_id' => $comment->id,
        ]);
    }

    /**
     * Create a reaction by a specific user.
     */
    public function byUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
        ]);
    }
}
