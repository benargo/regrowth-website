<?php

namespace App\Observers;

use App\Contracts\Actions\Navigation\CachesNavigationData;
use App\Models\PlannedAbsence;
use Illuminate\Support\Facades\Cache;

class PlannedAbsenceObserver implements CachesNavigationData
{
    /**
     * Handle the PlannedAbsence "created" event.
     */
    public function created(PlannedAbsence $plannedAbsence): void
    {
        Cache::tags(['attendance'])->flush();
        Cache::tags([self::CACHE_TAG])->flush();
    }

    /**
     * Handle the PlannedAbsence "updated" event.
     */
    public function updated(PlannedAbsence $plannedAbsence): void
    {
        Cache::tags(['attendance'])->flush();
        Cache::tags([self::CACHE_TAG])->flush();
    }

    /**
     * Handle the PlannedAbsence "deleted" event.
     */
    public function deleted(PlannedAbsence $plannedAbsence): void
    {
        Cache::tags(['attendance'])->flush();
        Cache::tags([self::CACHE_TAG])->flush();
    }

    /**
     * Handle the PlannedAbsence "restored" event.
     */
    public function restored(PlannedAbsence $plannedAbsence): void
    {
        Cache::tags(['attendance'])->flush();
        Cache::tags([self::CACHE_TAG])->flush();
    }
}
