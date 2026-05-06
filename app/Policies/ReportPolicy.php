<?php

namespace App\Policies;

use App\Models\Raids\Report;
use App\Models\User;

class ReportPolicy
{
    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionViaDiscordRoles('manage-reports');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Report $report): bool
    {
        return $user->hasPermissionViaDiscordRoles('manage-reports');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Report $report): bool
    {
        return $user->hasPermissionViaDiscordRoles('manage-reports');
    }
}
