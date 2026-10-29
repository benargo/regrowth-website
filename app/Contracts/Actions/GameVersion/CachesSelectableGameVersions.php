<?php

namespace App\Contracts\Actions\GameVersion;

interface CachesSelectableGameVersions
{
    /**
     * The cache tag holding the selectable game versions.
     */
    public const string CACHE_TAG = 'game-versions';

    /**
     * The request attribute holding the selectable game versions for the current request.
     */
    public const string REQUEST_KEY = 'game-versions:selectable';
}
