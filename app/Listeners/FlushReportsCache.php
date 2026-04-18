<?php

namespace App\Listeners;

use App\Contracts\Events\FlushesReportsCache;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Cache;

class FlushReportsCache implements ShouldBeUnique, ShouldQueue
{
    /**
     * Handle the event.
     */
    public function handle(FlushesReportsCache $event): void
    {
        Cache::tags(['reports'])->flush();
    }
}
