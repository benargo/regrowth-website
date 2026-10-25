<?php

namespace App\Console\Commands;

use App\Models\Report;
use App\Observers\ReportObserver;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

#[Signature('app:convert-report-times-to-app-timezone {--force : Convert without asking for confirmation}')]
#[Description('Convert reports.start_time and end_time from UTC wall-clock time to the app timezone. Run once only.')]
class ConvertReportTimesToAppTimezone extends Command
{
    /**
     * Rewrite every report's start and end time from UTC wall-clock time to the app
     * timezone in a single transaction, re-saving each report so its game version and
     * phase are re-derived, then flush the caches derived from reports.
     */
    public function handle(): int
    {
        if (! $this->option('force') && ! $this->confirm('This shifts every report time and must only run once. Continue?')) {
            $this->info('Aborted.');

            return self::FAILURE;
        }

        $converted = 0;

        ReportObserver::deferFlushing(function () use (&$converted): void {
            DB::transaction(function () use (&$converted): void {
                foreach (Report::lazyById(500) as $report) {
                    $report->start_time = $this->toAppTime($report->getRawOriginal('start_time'));
                    $report->end_time = $this->toAppTime($report->getRawOriginal('end_time'));
                    $report->save();

                    $converted++;
                }
            });
        });

        $this->info("Converted {$converted} report(s).");

        return self::SUCCESS;
    }

    /**
     * Convert a UTC wall-clock datetime string to the app timezone's wall-clock time.
     */
    private function toAppTime(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return Carbon::parse($value, 'UTC')->setTimezone(config('app.timezone'))->format('Y-m-d H:i:s');
    }
}
