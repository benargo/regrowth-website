<?php

namespace App\Policies;

use App\Models\Comment;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class CommentPolicy extends AuthorizationPolicy
{
    use HandlesAuthorization;

    /**
     * Determine if the user can access the "All Comments" page.
     */
    public function viewAny(User $user): bool
    {
        return $user->isAuthorizedTo('view-all-comments');
    }

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
     */
    public function markAsResolved(User $user, Comment $comment): bool
    {
        return $user->isAuthorizedTo('mark-comment-as-resolved');
    }

    /**
     * Determine if the user can react to a comment.
     */
    public function react(User $user, Comment $comment): bool
    {
        if ($comment->user_id === $user->id) {
            return false; // Users cannot react to their own comments
        }

        return $user->isAuthorizedTo('react-to-comments');
    }
}
