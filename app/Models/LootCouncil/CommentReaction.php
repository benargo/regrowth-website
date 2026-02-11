<?php

namespace App\Models\LootCouncil;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class CommentReaction extends Model
{
    /** @use HasFactory<\Database\Factories\LootCouncil\CommentReactionFactory> */
    use HasFactory;

    protected $table = 'lootcouncil_comments_reactions';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'comment_id',
        'user_id',
    ];

    /**
     * Boot the model.
     */
    protected static function booted(): void
    {
        static::saving(function (CommentReaction $reaction) {
            // Always fetch the comment by ID to handle cases where comment_id changed
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
    public function comment()
    {
        return $this->belongsTo(Comment::class);
    }

    /**
     * Get the user who made this reaction.
     *
     * @return BelongsTo<User, $this>
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
