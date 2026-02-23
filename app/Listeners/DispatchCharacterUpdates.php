<?php

namespace App\Listeners;

use App\Events\GuildRosterFetched;
use App\Jobs\UpdateCharacterFromRoster;
use Illuminate\Bus\Batch;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;

class DispatchCharacterUpdates implements ShouldQueue
{
    /**
     * Cache key used to track when roster updates were last dispatched.
     */
    protected string $cacheKey = 'guild.roster.updates.last_dispatched';

    /**
     * Throttle: do not dispatch more frequently than every 6 hours.
     */
    protected int $throttleSeconds = 21600;

    /**
     * Handle the event.
     */
    public function handle(GuildRosterFetched $event): void
    {
        if (! Cache::add($this->cacheKey, true, $this->throttleSeconds)) {
            return;
        }

        $jobs = [];

        foreach ($event->roster['members'] ?? [] as $memberData) {
            $jobs[] = new UpdateCharacterFromRoster($memberData);
        }

        if (! empty($jobs)) {
            Bus::batch($jobs)->before(function (Batch $batch) {
                Log::info('Starting batch to update characters from guild roster.', [
                    'total_jobs' => $batch->totalJobs,
                ]);
            })->progress(function (Batch $batch) {
                Log::info('Batch progress for updating characters from guild roster: '.$batch->progress().'% ('.$batch->processedJobs().'/'.$batch->totalJobs.')');
            })->catch(function (Batch $batch, Throwable $e) {
                Log::error('Batch to update characters from guild roster failed with error: '.$e->getMessage());
            })->then(function (Batch $batch) {
                Log::info('Batch to update characters from guild roster completed successfully.', [
                    'processed_jobs' => $batch->processedJobs(),
                ]);
            })->dispatch();
        }
    }
}
