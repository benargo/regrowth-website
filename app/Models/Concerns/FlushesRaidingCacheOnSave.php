<?php

namespace App\Models\Concerns;

use Illuminate\Support\Facades\Cache;

trait FlushesRaidingCacheOnSave
{
    public static function bootFlushesRaidingCacheOnSave(): void
    {
        $flush = fn () => Cache::tags(['raiding', 'events'])->flush();

        static::created($flush);
        static::updated($flush);
        static::deleted($flush);
    }
}
