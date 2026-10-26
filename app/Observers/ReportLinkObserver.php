<?php

namespace App\Observers;

use App\Jobs\Addon\BuildExportFile;
use App\Models\Report;
use App\Models\ReportLink;

class ReportLinkObserver
{
    /**
     * Handle the ReportLink "saved" event.
     */
    public function saved(ReportLink $reportLink): void
    {
        $this->linksChanged($reportLink);
    }

    /**
     * Handle the ReportLink "deleted" event.
     */
    public function deleted(ReportLink $reportLink): void
    {
        $this->linksChanged($reportLink);
    }

    /**
     * Flush the caches derived from report links and schedule an addon export rebuild.
     */
    private function linksChanged(ReportLink $reportLink): void
    {
        ReportObserver::flushCaches();

        BuildExportFile::scheduleForGameVersionIds(
            Report::whereKey([$reportLink->report_1, $reportLink->report_2])->pluck('game_version_id'),
        );
    }
}
