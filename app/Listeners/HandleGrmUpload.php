<?php

namespace App\Listeners;

use App\Events\GrmUploadProcessed;
use App\Jobs\ProcessGrmUpload;
use App\Jobs\SendGrmUploadNotification;
use App\Notifications\DiscordNotifiable;
use App\Notifications\GrmUploadFailed;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class HandleGrmUpload implements ShouldQueue
{
    /**
     * Handle the event.
     */
    public function handle(GrmUploadProcessed $event): void
    {
        dispatch(new SendGrmUploadNotification(
            $event->processedCount,
            $event->skippedCount,
            $event->warningCount,
            $event->errorCount,
            $event->errors,
        ));
    }

    /**
     * Handle a failure of the listener job itself.
     */
    public function failed(GrmUploadProcessed $event, Throwable $exception): void
    {
        Log::error('HandleGrmUpload listener failed: '.$exception->getMessage());

        Cache::put(ProcessGrmUpload::PROGRESS_CACHE_KEY, [
            'status' => 'failed',
            'step' => 2,
            'total' => 3,
            'message' => 'GRM upload handling failed: '.$exception->getMessage(),
            'processedCount' => $event->processedCount,
            'skippedCount' => $event->skippedCount,
            'warningCount' => $event->warningCount,
            'errorCount' => $event->errorCount,
            'errors' => $event->errors,
        ], now()->addHours(ProcessGrmUpload::PROGRESS_CACHE_TTL_HOURS));

        DiscordNotifiable::officer()->notify(
            new GrmUploadFailed(
                $event->processedCount,
                $event->errorCount,
                $event->errors,
                'GRM upload handling failed: '.$exception->getMessage()
            )
        );
    }

    /**
     * Get the tags that should be assigned to the job.
     *
     * @return array<int, string>
     */
    public function tags(): array
    {
        return ['grm-upload'];
    }
}
