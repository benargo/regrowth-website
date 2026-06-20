<?php

namespace App\Models;

use App\Events\CommentReactionCreated;
use App\Events\CommentReactionDeleted;
use Database\Factories\CommentReactionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

#[Table(name: 'pivot_comments_reactions')]
#[Fillable(['comment_id', 'user_id'])]
#[Hidden(['created_at', 'updated_at'])]
class CommentReaction extends Model
{
    /** @use HasFactory<CommentReactionFactory> */
    use HasFactory;

    /**
     * The event map for the model.
     *
     * @var array<string, string>
     */
    protected $dispatchesEvents = [
        'created' => CommentReactionCreated::class,
        'deleted' => CommentReactionDeleted::class,
    ];

    /**
     * Boot the model.
     */
    protected static function booted(): void
    {
        static::saving(function (CommentReaction $reaction) {
            $comment = Comment::find($reaction->comment_id);

            if ($comment && $reaction->user_id === $comment->user_id) {
                throw ValidationException::withMessages([
                    'user_id' => ['You cannot react to your own comment.'],
                ]);
            }
        });
    }

    /**
     * Get the comment that this reaction belongs to.
     *
     * @return BelongsTo<Comment, $this>
     */
    public function comment(): BelongsTo
    {
        return $this->belongsTo(Comment::class);
    }

    /**
     * Get the user who made this reaction.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
