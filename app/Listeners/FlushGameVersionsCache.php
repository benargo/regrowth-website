<?php

namespace App\Listeners;

use App\Contracts\Actions\GameVersion\CachesSelectableGameVersions;
use App\Contracts\Events\FlushesGameVersionsCache;
use App\Contracts\Http\Middleware\SharesDeferredCacheFlushes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Flushes the selectable game versions cache and the list memoised for the
 * current request, or records one pending flush while the request defers
 * cache flushes.
 */
class FlushGameVersionsCache implements CachesSelectableGameVersions, SharesDeferredCacheFlushes
{
    public function __construct(private Request $request) {}

    /**
     * Handle the event.
     */
    public function handle(FlushesGameVersionsCache $event): void
    {
        if ($this->request->attributes->get(self::DEFERRING_KEY)) {
            $this->request->attributes->set(self::PENDING_KEY, [
                ...$this->request->attributes->get(self::PENDING_KEY, []),
                self::class => fn () => $this->flush(),
            ]);

            return;
        }

        $this->flush();
    }

    private function flush(): void
    {
        Cache::tags([self::CACHE_TAG])->flush();
        $this->request->attributes->remove(self::REQUEST_KEY);
    }
}
