<?php

namespace App\Policies;

use App\Models\Comment;
use App\Models\CommentReaction;
use App\Models\User;

class CommentReactionPolicy
{
    /**
     * Determine if the user can react to a comment.
     */
    public function create(User $user, Comment $comment): bool
    {
        if ($comment->user_id === $user->id) {
            return false; // Users cannot react to their own comments
        }

        return $user->isAuthorizedTo('react-to-comments');
    }

    /**
     * Determine if the user can delete a reaction.
     *
     * Ownership is the only criterion: no permission grants the ability to
     * remove another user's reaction.
     */
    public function delete(User $user, CommentReaction $reaction): bool
    {
        return $reaction->user_id === $user->id;
    }
}
