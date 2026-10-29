<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WarcraftLogs\Guild;
use App\Models\WarcraftLogs\GuildTag;

/**
 * Governs the Warcraft Logs guild pages. Guilds and their tags share this
 * policy: tags come from Warcraft Logs, so the only thing an officer can
 * change on one is whether it counts toward attendance.
 */
class WarcraftLogsGuildPolicy extends AuthorizationPolicy
{
    /**
     * Determine whether the user can list guilds.
     */
    public function viewAny(User $user): bool
    {
        return $user->isAuthorizedTo('view-warcraft-logs-guilds');
    }

    /**
     * Determine whether the user can view a guild and its tags.
     */
    public function view(User $user, Guild $guild): bool
    {
        return $user->isAuthorizedTo('view-warcraft-logs-guilds');
    }

    /**
     * Determine whether the user can add a guild.
     */
    public function create(User $user): bool
    {
        return $user->isAuthorizedTo('create-warcraft-logs-guilds');
    }

    /**
     * Determine whether the user can change a guild's namespace, or a tag's
     * count_attendance flag.
     */
    public function update(User $user, Guild|GuildTag $model): bool
    {
        if ($model instanceof GuildTag) {
            return $user->isAuthorizedTo('update-warcraft-logs-tags');
        }

        return $user->isAuthorizedTo('update-warcraft-logs-guilds');
    }

    /**
     * Determine whether the user can delete a guild.
     */
    public function delete(User $user, Guild $guild): bool
    {
        return $user->isAuthorizedTo('delete-warcraft-logs-guilds');
    }
}
