<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

#[Signature('app:convert-report-times-to-app-timezone {--force : Convert without asking for confirmation}')]
#[Description('Convert reports.start_time and end_time from UTC wall-clock time to the app timezone. Run once only.')]
class ConvertReportTimesToAppTimezone extends Command
{
    /**
     * Rewrite every report's start and end time from UTC wall-clock time to the app
     * timezone in a single transaction, then flush the cached report date ranges.
     */
    public function handle(): int
    {
        if (! $this->option('force') && ! $this->confirm('This shifts every report time and must only run once. Continue?')) {
            $this->info('Aborted.');

            return self::FAILURE;
        }

        $converted = 0;

        DB::transaction(function () use (&$converted): void {
            DB::table('reports')->select('id', 'start_time', 'end_time')->chunkById(500, function (Collection $reports) use (&$converted): void {
                foreach ($reports as $report) {
                    DB::table('reports')->where('id', $report->id)->update([
                        'start_time' => $this->toAppTime($report->start_time),
                        'end_time' => $this->toAppTime($report->end_time),
                    ]);

                    $converted++;
                }
            });
        });

        Cache::tags(['reports'])->flush();
        Cache::tags(['attendance'])->flush();

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
