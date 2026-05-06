<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\User;

class EventPolicy
{
    /**
     * Determine whether the user can view a model.
     */
    public function view(User $user, Event $event): bool
    {
        return $user->hasPermissionViaDiscordRoles('view-raid-plans');
    }

    /**
     * Determine whether the user can update a model.
     */
    public function update(User $user, Event $event): bool
    {
        return $user->hasPermissionViaDiscordRoles('manage-raid-plans');
    }
}
