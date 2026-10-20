<?php

namespace App\Contracts\Http\Middleware;

interface SharesGameVersionSwitchFlash
{
    /**
     * The session key flashed by an explicit game version switch.
     */
    public const string SWITCHED_FLASH_KEY = 'game_version_switched';
}
