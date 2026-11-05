<?php

namespace App\Policies;

use App\Models\RecruitmentMacro;
use App\Models\User;

class RecruitmentMacroPolicy
{
    /**
     * Determine whether the user can see and copy the recruitment macros.
     */
    public function viewAny(User $user): bool
    {
        return $user->isAuthorizedTo('view-recruitment-macros');
    }

    /**
     * Determine whether the user can add recruitment macros.
     */
    public function create(User $user): bool
    {
        return $user->isAuthorizedTo('manage-recruitment-macros');
    }

    /**
     * Determine whether the user can reorder the recruitment macros.
     */
    public function reorder(User $user): bool
    {
        return $user->isAuthorizedTo('manage-recruitment-macros');
    }

    /**
     * Determine whether the user can edit the macro, whoever created it.
     */
    public function update(User $user, RecruitmentMacro $recruitmentMacro): bool
    {
        return $user->isAuthorizedTo('manage-recruitment-macros');
    }

    /**
     * Determine whether the user can move the macro to Recently deleted.
     */
    public function delete(User $user, RecruitmentMacro $recruitmentMacro): bool
    {
        return $user->isAuthorizedTo('delete-any-recruitment-macro')
            || $this->isCreatorWhoManages($user, $recruitmentMacro);
    }

    /**
     * Determine whether the user can restore the macro from Recently deleted.
     */
    public function restore(User $user, RecruitmentMacro $recruitmentMacro): bool
    {
        return $this->delete($user, $recruitmentMacro);
    }

    /**
     * Determine whether the user created the macro and holds the manage permission.
     */
    private function isCreatorWhoManages(User $user, RecruitmentMacro $recruitmentMacro): bool
    {
        return $recruitmentMacro->created_by !== null
            && $recruitmentMacro->created_by === $user->id
            && $user->isAuthorizedTo('manage-recruitment-macros');
    }
}
