<?php

namespace App\Jobs\RegrowthAddon\Export;

use App\Services\AttendanceCalculator\AttendanceCalculator;
use App\Services\AttendanceCalculator\CharacterAttendanceStats;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\SkipIfBatchCancelled;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class BuildPlayerAttendance implements ShouldQueue
{
    use Batchable, Queueable;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 60;

    /**
     * Define the middleware for the job.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [new SkipIfBatchCancelled];
    }

    /**
     * Execute the job.
     */
    public function handle(AttendanceCalculator $calculator): void
    {
        $stats = $calculator->wholeGuild();

        $data = $stats->map(fn (CharacterAttendanceStats $character) => [
            'id' => $character->id,
            'name' => $character->name,
            'attendance' => [
                'first_attendance' => $character->firstAttendance->setTimezone(config('app.timezone'))->toIso8601String(),
                'attended' => $character->reportsAttended,
                'total' => $character->totalReports,
                'percentage' => $character->percentage,
            ],
        ]);

        Cache::tags(['regrowth-addon', 'regrowth-addon:build'])
            ->put('regrowth-addon.build.attendance', $data, now()->addMinutes(15));

        Log::info('BuildPlayerAttendance job completed successfully.');
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('BuildPlayerAttendance job failed.', [
            'error' => $exception->getMessage(),
        ]);
    }

    /**
     * Get the tags that should be assigned to the job.
     *
     * @return array<int, string>
     */
    public function tags(): array
    {
        return ['regrowth-addon', 'regrowth-addon:build'];
    }
}
