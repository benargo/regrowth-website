<?php

namespace App\Listeners;

use App\Events\PlannedAbsenceDeleted;
use App\Events\PlannedAbsenceSaved;
use Illuminate\Support\Facades\Cache;

class FlushPlannedAbsencesCache
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(PlannedAbsenceDeleted|PlannedAbsenceSaved $event): void
    {
        Cache::tags(['planned_absences'])->flush();
    }
}
