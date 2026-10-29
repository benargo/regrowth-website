<?php

namespace App\Contracts\Actions\Navigation;

interface CachesNavigationData
{
    /**
     * The cache tag holding the Raiding and Loot flyout data.
     */
    public const string CACHE_TAG = 'navigation';

    /**
     * The number of minutes a viewer's flyout data stays cached.
     */
    public const int TTL_MINUTES = 5;

    /**
     * The cache and once-prop key for a game version id (or `none`) and a viewer id (or `guest`).
     */
    public const string KEY_FORMAT = 'navigation:%s:%s';
}
