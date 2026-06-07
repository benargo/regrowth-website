<?php

namespace App\Contracts;

/**
 * Marker interface opting a class's `blizzard_icons` Media Library collection into
 * the shared-storage + signed-URL pipeline.
 */
interface HasBlizzardIcons
{
    /**
     * The default size (in pixels) of Blizzard icons to request and store.
     */
    public const DEFAULT_MEDIA_SIZE = 56;

    /**
     * The name of the bundled questionmark icon used as a placeholder/fallback across the app.
     */
    public const BLIZZARD_UNKNOWN_ICON = 'inv_misc_questionmark';

    /**
     * The default file extension used for Blizzard icons on the render CDN.
     */
    public const DEFAULT_MEDIA_FILE_EXTENSION = 'jpg';

    /**
     * The Laravel Media Library collection name
     */
    public const MEDIA_COLLECTION = 'blizzard_icons';

    /**
     * The path prefix on the shared disk where Blizzard icons are stored.
     */
    public const STORAGE_PATH_PREFIX = 'blizzard-cdn';
}
