<?php

namespace App\Listeners;

use App\Events\WarcraftLogsGuildSaved;
use Illuminate\Support\Facades\Cache;

/**
 * Flushes the cached Warcraft Logs API responses when a guild is added or changes namespace.
 */
class FlushWarcraftLogsResponsesCache
{
    /**
     * Handle the event.
     */
    public function handle(WarcraftLogsGuildSaved $event): void
    {
        if (! $event->guild->isDirty('namespace')) {
            return;
        }

        Cache::tags(['warcraftlogs-api-response'])->flush();
    }
}
