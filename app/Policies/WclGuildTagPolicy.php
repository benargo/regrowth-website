<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WarcraftLogs\GuildTag;
use Illuminate\Auth\Access\HandlesAuthorization;

class WclGuildTagPolicy extends AuthorizationPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->isOfficer();
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, GuildTag $guildTag): bool
    {
        return $user->isOfficer();
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, GuildTag $guildTag): bool
    {
        return $user->isOfficer();
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, GuildTag $guildTag): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, GuildTag $guildTag): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, GuildTag $guildTag): bool
    {
        return false;
    }
}
