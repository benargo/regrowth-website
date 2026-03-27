<?php

namespace App\Listeners;

use App\Contracts\Events\SchedulesAddonExportBuild;
use App\Jobs\BuildAddonExportFile;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

class ScheduleAddonExportBuild implements ShouldQueue
{
    /**
     * Handle the event.
     */
    public function handle(SchedulesAddonExportBuild $event): void
    {
        BuildAddonExportFile::dispatch()->delay(now()->addSeconds(120));
    }

    /**
     * Handle a failure of the listener job itself.
     */
    public function failed(SchedulesAddonExportBuild $event, Throwable $exception): void
    {
        Log::error('ScheduleAddonExportBuild listener failed: '.$exception->getMessage());
    }

    /**
     * Get the tags that should be assigned to the job.
     *
     * @return array<int, string>
     */
    public function tags(): array
    {
        return ['regrowth-addon-export'];
    }
}
