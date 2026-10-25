<?php

namespace App\Listeners;

use App\Contracts\Actions\GameVersion\CachesSelectableGameVersions;
use App\Contracts\Events\FlushesGameVersionsCache;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Flushes the selectable game versions cache and the list memoised for the current request.
 */
class FlushGameVersionsCache implements CachesSelectableGameVersions
{
    public function __construct(private Request $request) {}

    /**
     * Handle the event.
     */
    public function handle(FlushesGameVersionsCache $event): void
    {
        $this->flush();
    }

    private function flush(): void
    {
        Cache::tags([self::CACHE_TAG])->flush();
        $this->request->attributes->remove(self::REQUEST_KEY);
    }
}
