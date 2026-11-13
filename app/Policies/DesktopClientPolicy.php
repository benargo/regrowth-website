<?php

namespace App\Policies;

use App\Models\DesktopClient;
use App\Models\User;

class DesktopClientPolicy
{
    /**
     * Determine whether the user can list their linked desktop apps.
     */
    public function viewAny(User $user): bool
    {
        return $user->isAuthorizedTo('use-desktop-client');
    }

    /**
     * Determine whether the user can link a desktop app.
     */
    public function create(User $user): bool
    {
        return $user->isAuthorizedTo('use-desktop-client');
    }

    /**
     * Determine whether the user can upload Guild Roster Manager data from the desktop app.
     */
    public function uploadGuildRoster(User $user): bool
    {
        return $user->isAuthorizedTo('use-desktop-client')
            && $user->isAuthorizedTo('upload-grm-rosters');
    }

    /**
     * Determine whether the user can sign out the desktop app.
     */
    public function delete(User $user, DesktopClient $desktopClient): bool
    {
        return $desktopClient->user()->is($user) || $user->isAuthorizedTo('revoke-any-desktop-client');
    }
}
