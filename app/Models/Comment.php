<?php

namespace App\Models;

use App\Casts\AsClassName;
use App\Casts\AsKeyType;
use App\Events\CommentCreated;
use App\Events\CommentDeleted;
use App\Events\CommentUpdated;
use Database\Factories\CommentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['commentable_id', 'commentable_type', 'user_id', 'body', 'is_resolved', 'deleted_by'])]
class Comment extends Model
{
    /** @use HasFactory<CommentFactory> */
    use HasFactory;

    use SoftDeletes;

    /**
     * The model's default values for attributes.
     */
    protected $attributes = [
        'is_resolved' => false,
    ];

    /**
     * The attributes that should be cast to native types.
     */
    protected $casts = [
        'commentable_id' => AsKeyType::class,
        'commentable_type' => AsClassName::class,
        'is_resolved' => 'boolean',
    ];

    /**
     * The event map for the model.
     *
     * @var array<string, string>
     */
    protected $dispatchesEvents = [
        'created' => CommentCreated::class,
        'deleted' => CommentDeleted::class,
        'updated' => CommentUpdated::class,
    ];

    /**
     * Get the parent commentable model.
     *
     * @return MorphTo<Model, $this>
     */
    public function commentable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the user who deleted this comment.
     *
     * @return BelongsTo<User, $this>
     */
    public function deletedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    /**
     * Determine whether this comment is a reply to another comment.
     */
    public function isReply(): bool
    {
        return $this->parent_id !== null;
    }

    /**
     * Get the comment this comment replies to, if any.
     *
     * @return BelongsTo<Comment, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Comment::class, 'parent_id');
    }

    /**
     * Get the reactions for this comment.
     *
     * @return HasMany<CommentReaction, $this>
     */
    public function reactions(): HasMany
    {
        return $this->hasMany(CommentReaction::class, 'comment_id');
    }

    /**
     * Get the replies to this comment, oldest first.
     *
     * A discussion reads chronologically, which is deliberately the opposite
     * of the top-level list's newest-first ordering.
     *
     * @return HasMany<Comment, $this>
     */
    public function replies(): HasMany
    {
        return $this->hasMany(Comment::class, 'parent_id')->oldest();
    }

    /**
     * Get the prior bodies recorded for this comment.
     *
     * @return HasMany<CommentRevision, $this>
     */
    public function revisions(): HasMany
    {
        return $this->hasMany(CommentRevision::class, 'comment_id');
    }

    /**
     * Scope a query to only include top-level comments.
     */
    #[Scope]
    protected function topLevel(Builder $query): void
    {
        $query->whereNull('parent_id');
    }

    /**
     * Get the user who wrote this comment.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
