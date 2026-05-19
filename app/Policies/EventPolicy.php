<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\User;

class EventPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewTemplates(User $user): bool
    {
        return $user->hasPermissionViaDiscordRoles('manage-raid-plans');
    }

    /**
     * Determine whether the user can view a model.
     */
    public function view(?User $user, Event $event): bool
    {
        // For templates, require 'manage-raid-plans' permission to view.
        if ($event->is_template) {
            return $user?->hasPermissionViaDiscordRoles('manage-raid-plans') ?? false;
        }

        // For regular events, if the event ended more than 2 weeks ago, require 'view-old-raid-plans' permission.
        if ($event->end_time->isBefore(now()->subWeeks(2))) {
            return $user?->hasPermissionViaDiscordRoles('view-old-raid-plans') ?? false;
        }

        // Otherwise, allow viewing for all users (including guests).
        return true;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionViaDiscordRoles('manage-raid-plans');
    }

    /**
     * Determine whether the user can update a model.
     */
    public function update(User $user, Event $event): bool
    {
        return $user->hasPermissionViaDiscordRoles('manage-raid-plans');
    }

    /**
     * Determine whether the user can delete a model.
     */
    public function delete(User $user, Event $event): bool
    {
        // For templates, require 'manage-raid-plans' permission to delete.
        if ($event->is_template) {
            return $user->hasPermissionViaDiscordRoles('manage-raid-plans');
        }

        // Otherwise, only allow deletion if the user is an administrator.
        return $user->is_admin;
    }
}
