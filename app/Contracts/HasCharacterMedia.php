<?php

namespace App\Contracts;

interface HasCharacterMedia
{
    /**
     * The default size (in pixels) of Blizzard character portraits to request and store.
     */
    public const DEFAULT_MEDIA_SIZE = 135;

    /**
     * The default file extension used for Blizzard character portraits on the render CDN.
     */
    public const DEFAULT_MEDIA_FILE_EXTENSION = 'jpg';

    /**
     * The Laravel Media Library collection name for character portraits.
     */
    public const MEDIA_COLLECTION = 'character_portraits';

    /**
     * The path prefix on the shared disk where character portraits are stored.
     */
    public const STORAGE_PATH_PREFIX = 'blizzard-cdn/characters';
}
