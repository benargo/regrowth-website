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
}
