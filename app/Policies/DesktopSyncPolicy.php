<?php

namespace App\Policies;

use App\Enums\GuildRosterManager\UploadSource;
use App\Models\DesktopClient;
use App\Models\GuildRosterManager\Upload;
use App\Models\User;

class DesktopSyncPolicy
{
    /**
     * Determine whether the user can write to the desktop sync from the given desktop client.
     */
    public function writeSync(User $user, Upload $upload, DesktopClient $desktopClient): bool
    {
        return $upload->source === UploadSource::Desktop
            && $upload->user_id === $user->id
            && $upload->desktop_client_id === $desktopClient->id;
    }
}
