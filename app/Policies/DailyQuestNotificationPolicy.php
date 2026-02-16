<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class DailyQuestNotificationPolicy extends AuthorizationPolicy
{
    use HandlesAuthorization;

    /**
     * Create a new policy instance.
     */
    public function __construct()
    {
        //
    }

    public function store(User $user): bool
    {
        return $this->userIsOfficer($user);
    }
}
