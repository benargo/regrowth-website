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
     * The Laravel Media Library collection name for full-body character renders.
     *
     * Distinct from MEDIA_COLLECTION: that holds the 135px avatar used across
     * the roster, this holds the large transparent-background `main-raw` render
     * used by the homepage officer cards. Both collections are singleFile(), so
     * they must stay separate or each would evict the other.
     */
    public const MEDIA_COLLECTION_RENDER = 'character_renders';

    /**
     * The path prefix on the shared disk where character portraits are stored.
     */
    public const STORAGE_PATH_PREFIX = 'blizzard-cdn/characters';

    /**
     * How long, in seconds, a character Blizzard has no portrait for is remembered: three days.
     */
    public const PORTRAIT_MISSING_FOR_SECONDS = 259200;
}
