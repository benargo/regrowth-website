<?php

namespace App\Models\Concerns;

use Illuminate\Support\Facades\Cache;

trait FlushesRaidingCacheOnSave
{
    public static function bootFlushesRaidingCacheOnSave(): void
    {
        $flush = fn () => static::flushRaidingCache();

        static::created($flush);
        static::updated($flush);
        static::deleted($flush);
    }

    /**
     * Flush every cached entry tagged `raiding` and `events`.
     */
    public static function flushRaidingCache(): void
    {
        Cache::tags(['raiding', 'events'])->flush();
    }
}
