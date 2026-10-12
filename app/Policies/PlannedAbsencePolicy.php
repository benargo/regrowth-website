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
        return $user->isAuthorizedTo('view-planned-absences');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, PlannedAbsence $plannedAbsence): bool
    {
        return $user->isAuthorizedTo('view-planned-absences') || $plannedAbsence->createdBy()->is($user);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->isAuthorizedTo('create-planned-absences');
    }

    /**
     * Determine whether the user can backdate planned absences.
     */
    public function backdate(User $user): bool
    {
        return $user->isAuthorizedTo('backdate-planned-absences');
    }

    /**
     * Determine whether the user can update the model. Users with permission
     * can update any absence; the creator can update their own until it starts.
     */
    public function update(User $user, PlannedAbsence $plannedAbsence): bool
    {
        return $user->isAuthorizedTo('update-planned-absences')
            || ($plannedAbsence->createdBy()->is($user) && ! $plannedAbsence->hasStarted());
    }

    /**
     * Determine whether the user can delete the model. Users with permission
     * can delete any absence; the creator can delete their own until it starts.
     */
    public function delete(User $user, PlannedAbsence $plannedAbsence): bool
    {
        return $user->isAuthorizedTo('delete-planned-absences')
            || ($plannedAbsence->createdBy()->is($user) && ! $plannedAbsence->hasStarted());
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, ?PlannedAbsence $plannedAbsence = null): bool
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
