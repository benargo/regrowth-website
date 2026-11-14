<?php

namespace App\Policies;

use App\Models\GuildRosterManager\Ban;
use App\Models\User;

class BanPolicy
{
    /**
     * Determine whether the user can view the ban log.
     */
    public function viewAny(User $user): bool
    {
        return $user->isAuthorizedTo('view-grm-bans');
    }

    /**
     * Determine whether the user can view a ban.
     */
    public function view(User $user, Ban $ban): bool
    {
        return $user->isAuthorizedTo('view-grm-bans');
    }

    /**
     * Determine whether the user can see the reason recorded with a ban.
     */
    public function viewReason(User $user, Ban $ban): bool
    {
        return $user->isAuthorizedTo('view-grm-ban-reasons');
    }

    /**
     * Determine whether the user can send new bans through the desktop app.
     */
    public function create(User $user): bool
    {
        return $user->isAuthorizedTo('create-grm-bans');
    }

    /**
     * Determine whether the user can send changes to bans through the desktop app.
     */
    public function update(User $user, ?Ban $ban = null): bool
    {
        return $user->isAuthorizedTo('update-grm-bans');
    }

    /**
     * Ban rows are never deleted.
     */
    public function delete(User $user, Ban $ban): bool
    {
        return false;
    }

    /**
     * Ban rows are never permanently deleted.
     */
    public function forceDelete(User $user, Ban $ban): bool
    {
        return false;
    }
}
