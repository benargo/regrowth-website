<?php

namespace App\Policies;

use App\Contracts\Models\DatasetModel;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class DatasetPolicy extends AuthorizationPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionViaDiscordRoles('edit-datasets');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, DatasetModel $model): bool
    {
        return $user->hasPermissionViaDiscordRoles('edit-datasets');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionViaDiscordRoles('edit-datasets');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, DatasetModel $model): bool
    {
        return $user->hasPermissionViaDiscordRoles('edit-datasets');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, DatasetModel $model): bool
    {
        return $user->hasPermissionViaDiscordRoles('edit-datasets');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, DatasetModel $model): bool
    {
        return $user->is_admin ?: false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, DatasetModel $model): bool
    {
        return $user->is_admin ?: false;
    }
}
