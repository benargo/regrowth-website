<?php

namespace App\Contracts;

/**
 * Marker interface opting a class's `shadow_avatars` Media Library collection into
 * the shared Blizzard mirror directory.
 */
interface HasShadowAvatars
{
    /**
     * The Laravel Media Library collection name for race/gender shadow avatars.
     */
    public const MEDIA_COLLECTION = 'shadow_avatars';

    /**
     * The file extension used for shadow avatars on the render CDN.
     */
    public const DEFAULT_MEDIA_FILE_EXTENSION = 'jpg';

    /**
     * The path on the shared disk where shadow avatars are stored.
     */
    public const STORAGE_PATH_PREFIX = 'blizzard-cdn/shadow/avatar';

    /**
     * How long, in seconds, a shadow avatar the render CDN does not have is remembered: seven days.
     */
    public const SHADOW_AVATAR_MISSING_FOR_SECONDS = 604800;
}
