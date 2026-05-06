<?php

namespace App\Policies;

use App\Models\Boss;
use App\Models\User;

class BossPolicy
{
    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Boss $boss): bool
    {
        return $user->hasPermissionViaDiscordRoles('manage-boss-strategies');
    }
}
