<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ViewAsRolePolicy extends AuthorizationPolicy
{
    use HandlesAuthorization;

    /**
     * Create a new policy instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Determine if the user can view as the specified role.
     */
    public function viewAsRole(User $user): bool
    {
        return $this->userIsOfficer($user);
    }
}
