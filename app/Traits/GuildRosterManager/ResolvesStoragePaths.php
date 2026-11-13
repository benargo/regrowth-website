<?php

namespace App\Traits\GuildRosterManager;

use App\Models\GameVersion;

trait ResolvesStoragePaths
{
    /**
     * Get the local-disk path of the given game version's latest GRM upload.
     */
    protected function grmUploadPath(GameVersion $gameVersion): string
    {
        return str_replace('{game_version}', $gameVersion->slug, config()->string('services.grm.upload_path'));
    }

    /**
     * Get the local-disk directory holding the given game version's archived GRM
     * uploads, or the archive root for all game versions when none is given.
     */
    protected function grmArchivePath(?GameVersion $gameVersion = null): string
    {
        $template = config()->string('services.grm.archive_path');

        if ($gameVersion === null) {
            return str($template)->before('{game_version}')->rtrim('/')->toString();
        }

        return str_replace('{game_version}', $gameVersion->slug, $template);
    }
}
