<?php

namespace Database\Factories;

use App\Models\Comment;
use App\Models\CommentRevision;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends Factory<CommentRevision>
 */
class CommentRevisionFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Model>
     */
    protected $model = CommentRevision::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'comment_id' => Comment::factory(),
            'body' => $this->faker->paragraph(),
            'edited_by' => User::factory(),
        ];
    }

    /**
     * Create a revision for a specific comment.
     */
    public function forComment(Comment $comment): static
    {
        return $this->state(fn (array $attributes) => [
            'comment_id' => $comment->id,
        ]);
    }

    /**
     * Create a revision recorded against a specific editor.
     */
    public function editedBy(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'edited_by' => $user->id,
        ]);
    }
}
