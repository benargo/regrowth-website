<?php

namespace App\Policies;

use App\Models\Comment;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class CommentPolicy extends AuthorizationPolicy
{
    use HandlesAuthorization;

    /**
     * Determine if the user can create comments on loot items.
     */
    public function create(User $user): bool
    {
        return $user->isAuthorizedTo('comment-on-loot-items');
    }

    /**
     * Determine if the user can delete a comment.
     */
    public function delete(User $user, Comment $comment): bool
    {
        if ($user->isAuthorizedTo('delete-any-comment')) {
            return true;
        }

        return $comment->user_id === $user->id;
    }

    /**
     * Determine if the user can update a comment.
     */
    public function update(User $user, Comment $comment): bool
    {
        if ($user->isAuthorizedTo('edit-any-comment')) {
            return true;
        }

        if ($comment->is_resolved) {
            return false; // Only officers can edit resolved comments
        }

        return $comment->user_id === $user->id;
    }

    /**
     * Determine if the user can mark a comment as resolved.
     *
     * Resolution is a thread-level concept: only a root comment can be
     * resolved, never an individual reply within the discussion.
     */
    public function markAsResolved(User $user, Comment $comment): bool
    {
        if ($comment->isReply()) {
            return false;
        }

        return $user->isAuthorizedTo('mark-comment-as-resolved');
    }

    /**
     * Determine if the user can reply to a comment.
     *
     * Replies need the same permission as any other comment; the only extra
     * constraint is that a deleted comment's thread is closed to new replies.
     */
    public function reply(User $user, Comment $comment): bool
    {
        if ($comment->trashed()) {
            return false;
        }

        return $user->isAuthorizedTo('comment-on-loot-items');
    }
}
