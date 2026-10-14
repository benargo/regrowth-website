<?php

namespace App\Actions\Discord;

use App\Models\DiscordRole;
use App\Models\User;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Syncs a user's Discord roles to the given role IDs, keeping only roles recognised by the app.
 */
class SyncUserDiscordRoles
{
    use AsAction;

    /**
     * @param  array<int, string>  $roleIds
     */
    public function handle(User $user, array $roleIds): void
    {
        $user->discordRoles()->sync(DiscordRole::whereIn('id', $roleIds)->pluck('id')->all());
    }
}
