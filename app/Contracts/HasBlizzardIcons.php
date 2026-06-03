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
    public const BLIZZARD_ICON_SIZE = 56;

    /**
     * The name of the bundled questionmark icon used as a placeholder/fallback across the app.
     */
    public const BLIZZARD_UNKNOWN_ICON = 'inv_misc_questionmark';
}
