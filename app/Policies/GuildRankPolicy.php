<?php

namespace App\Policies;

use App\Models\GuildRank;
use App\Models\User;

class GuildRankPolicy
{
    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->isOfficer();
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, GuildRank $guildRank): bool
    {
        return $user->isOfficer();
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, GuildRank $guildRank): bool
    {
        return $user->isOfficer();
    }
}
