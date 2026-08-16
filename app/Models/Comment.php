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
     * Resolved `withTrashed()` because a thread outlives its root: deleting a
     * root leaves a tombstone with live replies beneath it, and those replies
     * must still be able to name their parent. Without this a reply under a
     * deleted root reports no parent at all, which reads identically to being
     * a root itself.
     *
     * @return BelongsTo<Comment, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Comment::class, 'parent_id')->withTrashed();
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
     * Scope to the root comments a listing should display.
     *
     * A root is listable when it is live, or when it is trashed but still has
     * live replies — its tombstone has to render so the surviving discussion
     * beneath it has somewhere to hang. A trashed root with nothing under it
     * is dropped entirely.
     *
     * `orWhereHas('replies')` counts only non-trashed replies, because the
     * `replies()` relation does not apply `withTrashed()`.
     */
    #[Scope]
    protected function listableRoots(Builder $query): void
    {
        $query->withTrashed()
            ->topLevel()
            ->where(fn (Builder $nested) => $nested
                ->whereNull('deleted_at')
                ->orWhereHas('replies')
            );
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
