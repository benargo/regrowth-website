<?php

namespace App\Observers;

use App\Jobs\BuildAddonExportFile;
use App\Models\ReportLink;
use Illuminate\Support\Facades\Cache;

class ReportLinkObserver
{
    /**
     * Handle the ReportLink "saved" event.
     */
    public function saved(ReportLink $reportLink): void
    {
        $this->linksChanged();
    }

    /**
     * Handle the ReportLink "deleted" event.
     */
    public function deleted(ReportLink $reportLink): void
    {
        $this->linksChanged();
    }

    /**
     * Flush the caches derived from report links and schedule an addon export rebuild.
     */
    private function linksChanged(): void
    {
        Cache::tags(['attendance'])->flush();
        Cache::tags(['reports'])->flush();

        BuildAddonExportFile::dispatch()->delay(now()->addSeconds(120));
    }
}
