<?php

namespace App\Listeners;

use App\Events\ReportUpdated;
use Illuminate\Support\Facades\Cache;

class FlushWarcraftLogsCache
{
    /**
     * Handle the event.
     */
    public function handle(ReportUpdated $event): void
    {
        Cache::tags('warcraftlogs')->flush();
    }
}
