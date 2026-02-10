<?php

namespace App\Policies;

use App\Models\LootCouncil\ItemComment;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ItemCommentPolicy extends AuthorizationPolicy
{
    use HandlesAuthorization;

    /**
     * Determine if the user can create comments on loot items.
     */
    public function create(User $user): bool
    {
        return $user->canCommentOnLootItems();
    }

    /**
     * Determine if the user can delete a comment.
     */
    public function delete(User $user, ItemComment $comment): bool
    {
        if ($this->userIsOfficer($user)) {
            return true;
        }

        return $comment->user_id === $user->id;
    }

    /**
     * Determine if the user can update a comment.
     */
    public function update(User $user, ItemComment $comment): bool
    {
        if ($this->userIsOfficer($user)) {
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
    public function markAsResolved(User $user, ItemComment $comment): bool
    {
        return $this->userIsOfficer($user);
    }
}
