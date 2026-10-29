<?php

namespace App\Contracts;

/**
 * Constants shared by classes that fetch or store Discord avatars.
 */
interface HasDiscordAvatars
{
    /**
     * The base URL of the Discord CDN.
     */
    public const CDN_BASE_URL = 'https://cdn.discordapp.com';

    /**
     * The name of the thumbnail conversion generated for stored avatars.
     */
    public const AVATAR_CONVERSION = 'thumb';

    /**
     * The default size (in pixels) of stored avatar thumbnails.
     */
    public const DEFAULT_MEDIA_SIZE = 160;

    /**
     * The number of default Discord avatars.
     */
    public const DEFAULT_AVATAR_COUNT = 6;

    /**
     * The storage path prefix of the stored default avatars.
     */
    public const STORAGE_PATH_PREFIX = 'discord-cdn/embed/avatars';
}
