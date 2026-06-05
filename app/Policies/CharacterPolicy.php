<?php

namespace App\Policies;

use App\Models\Character;
use App\Models\User;

/**
 * Wired via Laravel's policy auto-discovery (Character → CharacterPolicy).
 */
class CharacterPolicy
{
    /**
     * Determine whether the user can create characters.
     */
    public function create(User $user): bool
    {
        return $user->isAuthorizedTo('create-characters');
    }

    /**
     * Determine whether the user can update the character.
     */
    public function update(User $user, Character $character): bool
    {
        return $user->isAuthorizedTo('update-characters');
    }

    /**
     * Determine whether the user can delete the character.
     */
    public function delete(User $user, Character $character): bool
    {
        return $user->isAuthorizedTo('delete-characters');
    }
}
