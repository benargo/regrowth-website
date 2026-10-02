<?php

namespace App\Traits\GuildRosterManager;

use App\Models\GameVersion;

trait ResolvesUploadPath
{
    /**
     * Get the local-disk path of the given game version's latest GRM upload.
     */
    protected function grmUploadPath(GameVersion $gameVersion): string
    {
        return str_replace('{game_version}', $gameVersion->slug, config()->string('services.guild_roster_manager.upload_path'));
    }
}
