<?php

namespace App\Jobs\RegrowthAddon\Export;

use App\Models\LootCouncil\Priority;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\SkipIfBatchCancelled;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class BuildPriorities implements ShouldQueue
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
        $data = Priority::has('items')->get()->map(function (Priority $priority) {
            return [
                'id' => $priority->id,
                'name' => $priority->title,
                'icon' => $priority->media['media_name'] ?? null,
            ];
        });

        Cache::tags(['regrowth-addon', 'regrowth-addon:build'])
            ->put('regrowth-addon.build.priorities', $data, now()->addMinutes(15));

        Log::info('BuildPriorities job completed successfully.');
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('BuildPriorities job failed.', [
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
