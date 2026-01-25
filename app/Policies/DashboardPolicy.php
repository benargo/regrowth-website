<?php

namespace App\Policies;

use App\Models\User;
use App\Policies\AuthorizationPolicy;
use Illuminate\Auth\Access\HandlesAuthorization;

class DashboardPolicy extends AuthorizationPolicy
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
     * Determine if the user can access the dashboard.
     */
    public function access(User $user): bool
    {
        return $this->userIsOfficer($user);
    }
}
