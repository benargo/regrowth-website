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
     * Determine whether the user can update the model.
     */
    public function update(User $user, PlannedAbsence $plannedAbsence): bool
    {
        return $user->isAuthorizedTo('update-planned-absences')
            || $this->creatorCanChange($user, $plannedAbsence);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, PlannedAbsence $plannedAbsence): bool
    {
        return $user->isAuthorizedTo('delete-planned-absences')
            || $this->creatorCanChange($user, $plannedAbsence);
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

    /**
     * Determine whether the user created the absence and it starts today or later.
     */
    private function creatorCanChange(User $user, PlannedAbsence $plannedAbsence): bool
    {
        return $plannedAbsence->createdBy()->is($user) && $plannedAbsence->start_date->gte(today());
    }
}
