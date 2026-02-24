<?php

namespace App\Jobs\RegrowthAddon\Export;

use Carbon\Carbon;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\SkipIfBatchCancelled;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class BuildDataFile implements ShouldQueue
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
    public function handle(): void
    {
        $buildCache = Cache::tags(['regrowth-addon', 'regrowth-addon:build']);

        $data = [
            'system' => [
                'date_generated' => Carbon::now()->unix(),
            ],
            'priorities' => $buildCache->get('regrowth-addon.build.priorities', collect()),
            'items' => $buildCache->get('regrowth-addon.build.items', collect()),
            'players' => $buildCache->get('regrowth-addon.build.attendance', collect()),
            'councillors' => $buildCache->get('regrowth-addon.build.councillors', collect()),
        ];

        Storage::disk('local')->put('addon/export.json', json_encode($data));

        Cache::tags(['regrowth-addon:build'])->flush();

        Log::info('Addon export data file built successfully.');
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('BuildDataFile job failed.', [
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
