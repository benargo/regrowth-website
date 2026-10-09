<?php

namespace App\Observers;

use App\Actions\WarcraftLogs\DeriveReportGameVersion;
use App\Models\Report;
use Illuminate\Support\Facades\Cache;

class ReportObserver
{
    /**
     * Handle the Report "saving" event.
     */
    public function saving(Report $report): void
    {
        DeriveReportGameVersion::run($report);
    }

    /**
     * Handle the Report "created" event.
     */
    public function created(Report $report): void
    {
        $this->flushCaches();
    }

    /**
     * Handle the Report "updated" event.
     */
    public function updated(Report $report): void
    {
        $this->flushCaches();
    }

    /**
     * Flush the caches derived from reports.
     */
    private function flushCaches(): void
    {
        Cache::tags(['attendance'])->flush();
        Cache::tags(['reports'])->flush();
    }
}
