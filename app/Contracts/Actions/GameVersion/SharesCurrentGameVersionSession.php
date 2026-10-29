<?php

namespace App\Contracts\Actions\GameVersion;

interface SharesCurrentGameVersionSession
{
    /**
     * The session key holding the slug of the current game version.
     */
    public const string SESSION_KEY = 'current_game_version';
}
