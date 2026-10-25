<?php

namespace App\Listeners;

use App\Contracts\Events\FlushesRaidingCache;
use App\Contracts\Http\Middleware\SharesDeferredCacheFlushes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Flushes the raiding and events cache tags, or records one pending flush while the request defers cache flushes.
 */
class FlushRaidingCache implements SharesDeferredCacheFlushes
{
    public function __construct(private Request $request) {}

    /**
     * Handle the event.
     */
    public function handle(FlushesRaidingCache $event): void
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
        Cache::tags(['raiding', 'events'])->flush();
    }
}
