<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ItemPolicy extends AuthorizationPolicy
{
    use HandlesAuthorization;

    /**
     * Determine if the user can access the loot priority manager.
     */
    public function access(User $user): bool
    {
        return $this->userIsMemberOrAbove($user);
    }

    /**
     * Determine if the user can manage loot priorities.
     */
    public function edit(User $user): bool
    {
        return $this->userIsOfficer($user);
    }
}
