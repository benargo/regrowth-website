<?php

namespace App\Contracts\Models;

/**
 * A class that works with the media collections of a game version.
 */
interface DefinesGameVersionMediaCollections
{
    /**
     * The wide header image shown above the game version's pages.
     */
    public const string MEDIA_BANNER = 'banner';

    /**
     * A single-colour mark for compact places such as navigation.
     */
    public const string MEDIA_ICON = 'icon';

    /**
     * The full-colour game logo.
     */
    public const string MEDIA_LOGO = 'logo';

    /**
     * Every image collection. Each holds a single file.
     *
     * @var list<string>
     */
    public const array MEDIA_COLLECTIONS = [
        self::MEDIA_BANNER,
        self::MEDIA_ICON,
        self::MEDIA_LOGO,
    ];
}
