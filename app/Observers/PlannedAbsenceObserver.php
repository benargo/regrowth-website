<?php

namespace App\Observers;

use App\Models\PlannedAbsence;
use Illuminate\Support\Facades\Cache;

class PlannedAbsenceObserver
{
    /**
     * Handle the PlannedAbsence "created" event.
     */
    public function created(PlannedAbsence $plannedAbsence): void
    {
        Cache::tags(['attendance'])->flush();
    }

    /**
     * Handle the PlannedAbsence "updated" event.
     */
    public function updated(PlannedAbsence $plannedAbsence): void
    {
        Cache::tags(['attendance'])->flush();
    }

    /**
     * Handle the PlannedAbsence "deleted" event.
     */
    public function deleted(PlannedAbsence $plannedAbsence): void
    {
        Cache::tags(['attendance'])->flush();
    }

    /**
     * Handle the PlannedAbsence "restored" event.
     */
    public function restored(PlannedAbsence $plannedAbsence): void
    {
        Cache::tags(['attendance'])->flush();
    }
}
