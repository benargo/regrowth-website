<?php

namespace App\Listeners;

use App\Contracts\Events\FlushesAttendanceCache;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Cache;

class FlushAttendanceCache implements ShouldBeUnique, ShouldQueue
{
    /**
     * Handle the event.
     */
    public function handle(FlushesAttendanceCache $event): void
    {
        Cache::tags(['attendance'])->flush();
    }
}
