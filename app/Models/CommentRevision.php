<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['comment_id', 'body', 'edited_by'])]
class CommentRevision extends Model
{
    use HasFactory;

    /**
     * This table records `created_at` only — a revision is immutable.
     */
    public const UPDATED_AT = null;

    /**
     * Get the comment this revision belongs to.
     *
     * @return BelongsTo<Comment, $this>
     */
    public function comment(): BelongsTo
    {
        return $this->belongsTo(Comment::class);
    }

    /**
     * Get the user who performed the edit that produced this revision.
     *
     * This is not necessarily the comment's author — officers holding
     * `edit-any-comment` moderate other users' comments.
     *
     * @return BelongsTo<User, $this>
     */
    public function editedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'edited_by');
    }
}
