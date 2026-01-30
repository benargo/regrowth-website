<?php

namespace App\Policies;

use App\Models\TBC\Phase;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class PhasePolicy extends AuthorizationPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Phase $phase): bool
    {
        return $this->userIsOfficer($user);
    }
}
