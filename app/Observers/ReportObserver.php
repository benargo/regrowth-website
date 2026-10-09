<?php

namespace App\Observers;

use App\Actions\WarcraftLogs\DeriveReportGameVersion;
use App\Models\Report;
use Closure;
use Illuminate\Support\Facades\Cache;

class ReportObserver
{
    private static bool $isDeferringFlush = false;

    private static bool $hasPendingFlush = false;

    /**
     * Run the callback with cache flushing deferred, then flush once if any
     * report was created or updated. Use it around bulk report re-saves.
     *
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    public static function deferFlushing(Closure $callback): mixed
    {
        if (self::$isDeferringFlush) {
            return $callback();
        }

        self::$isDeferringFlush = true;

        try {
            return $callback();
        } finally {
            self::$isDeferringFlush = false;

            if (self::$hasPendingFlush) {
                self::$hasPendingFlush = false;
                self::flushCaches();
            }
        }
    }

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
        $this->requestFlush();
    }

    /**
     * Handle the Report "updated" event.
     */
    public function updated(Report $report): void
    {
        $this->requestFlush();
    }

    /**
     * Flush now, or mark a flush as pending while flushing is deferred.
     */
    private function requestFlush(): void
    {
        if (self::$isDeferringFlush) {
            self::$hasPendingFlush = true;

            return;
        }

        self::flushCaches();
    }

    /**
     * Flush the caches derived from reports.
     */
    private static function flushCaches(): void
    {
        Cache::tags(['attendance'])->flush();
        Cache::tags(['reports'])->flush();
    }
}
