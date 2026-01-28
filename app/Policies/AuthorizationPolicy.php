<?php

namespace App\Policies;

use App\Models\User;

abstract class AuthorizationPolicy
{
    /**
     * Create a new policy instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Determine if the user is an Officer.
     */
    public function userIsOfficer(User $user): bool
    {
        return $user->isOfficer() ?? false;
    }

    /**
     * Determine if the user is a Raider.
     */
    public function userIsRaider(User $user): bool
    {
        return $user->isRaider() ?? false;
    }

    /**
     * Determine if the user is a Member.
     */
    public function userIsMember(User $user): bool
    {
        return $user->isMember() ?? false;
    }

    /**
     * Determine if the user is a Member or above (Member, Raider, or Officer).
     */
    public function userIsMemberOrAbove(User $user): bool
    {
        return $this->userIsOfficer($user)
            || $this->userIsRaider($user)
            || $this->userIsMember($user);
    }
}
