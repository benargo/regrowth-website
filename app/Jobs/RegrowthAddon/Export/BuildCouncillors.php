<?php

namespace App\Jobs\RegrowthAddon\Export;

use App\Models\Character;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\SkipIfBatchCancelled;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class BuildCouncillors implements ShouldQueue
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
        $data = Character::where('is_loot_councillor', true)
            ->with('rank')
            ->orderBy('name')
            ->get()
            ->map(function (Character $character) {
                return [
                    'id' => $character->id,
                    'name' => $character->name,
                    'rank' => $character->rank?->name,
                ];
            });

        Cache::tags(['regrowth-addon', 'regrowth-addon:build'])
            ->put('regrowth-addon.build.councillors', $data, now()->addMinutes(15));

        Log::info('BuildCouncillors job completed successfully.');
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('BuildCouncillors job failed.', [
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
