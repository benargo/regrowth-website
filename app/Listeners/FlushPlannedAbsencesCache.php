<?php

namespace App\Listeners;

use App\Contracts\Events\PlannedAbsenceModified;
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
    public function handle(PlannedAbsenceModified $event): void
    {
        Cache::tags(['planned_absences'])->flush();
    }
}
