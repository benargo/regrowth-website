<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\User;

class EventPolicy
{
    /**
     * Determine whether the user can view a model.
     */
    public function view(?User $user, Event $event): bool
    {
        if ($event->end_time->isBefore(now()->subHours(2))) {
            return $user?->hasPermissionViaDiscordRoles('view-old-raid-plans') ?? false;
        }

        return true;
    }

    /**
     * Determine whether the user can update a model.
     */
    public function update(User $user, Event $event): bool
    {
        return $user->hasPermissionViaDiscordRoles('manage-raid-plans');
    }
}
