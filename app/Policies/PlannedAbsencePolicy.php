<?php

namespace App\Policies;

use App\Models\PlannedAbsence;
use App\Models\User;

class PlannedAbsencePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionViaDiscordRoles('view-planned-absences');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, PlannedAbsence $plannedAbsence): bool
    {
        return $user->hasPermissionViaDiscordRoles('view-planned-absences') || $plannedAbsence->createdBy()->is($user);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionViaDiscordRoles('create-planned-absences');
    }

    /**
     * Determine whether the user can create models for others.
     */
    public function createForOthers(User $user): bool
    {
        return $user->hasPermissionViaDiscordRoles('manage-planned-absences');
    }

    /**
     * Determine whether the user can create backdated planned absences.
     */
    public function createBackdated(User $user): bool
    {
        return $user->hasPermissionViaDiscordRoles('manage-planned-absences');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, PlannedAbsence $plannedAbsence): bool
    {
        return $user->hasPermissionViaDiscordRoles('update-planned-absences') || $plannedAbsence->createdBy()->is($user);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, PlannedAbsence $plannedAbsence): bool
    {
        return $user->hasPermissionViaDiscordRoles('delete-planned-absences') || $plannedAbsence->createdBy()->is($user);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, PlannedAbsence $plannedAbsence): bool
    {
        return $user->isAdmin() ?? false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, PlannedAbsence $plannedAbsence): bool
    {
        return $user->isAdmin() ?? false;
    }
}
