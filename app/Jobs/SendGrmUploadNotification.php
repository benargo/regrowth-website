<?php

namespace App\Jobs;

use App\Notifications\DiscordNotifiable;
use App\Notifications\GrmUploadCompleted;
use App\Notifications\GrmUploadFailed;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

class SendGrmUploadNotification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<int, string>  $errors
     */
    public function __construct(
        public readonly int $processedCount,
        public readonly int $skippedCount,
        public readonly int $warningCount,
        public readonly int $errorCount,
        public readonly array $errors,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        if ($this->errorCount > 0) {
            DiscordNotifiable::officer()->notify(
                new GrmUploadFailed($this->processedCount, $this->errorCount, $this->errors)
            );

            Cache::put(ProcessGrmUpload::PROGRESS_CACHE_KEY, [
                'status' => 'failed',
                'step' => 3,
                'total' => 3,
                'message' => 'Upload completed with errors.',
                'processedCount' => $this->processedCount,
                'skippedCount' => $this->skippedCount,
                'warningCount' => $this->warningCount,
                'errorCount' => $this->errorCount,
                'errors' => $this->errors,
            ], now()->addHours(ProcessGrmUpload::PROGRESS_CACHE_TTL_HOURS));
        } else {
            DiscordNotifiable::officer()->notify(
                new GrmUploadCompleted($this->processedCount, $this->skippedCount, $this->warningCount)
            );

            Cache::put(ProcessGrmUpload::PROGRESS_CACHE_KEY, [
                'status' => 'completed',
                'step' => 3,
                'total' => 3,
                'message' => 'Upload complete!',
                'processedCount' => $this->processedCount,
                'skippedCount' => $this->skippedCount,
                'warningCount' => $this->warningCount,
                'errorCount' => 0,
                'errors' => [],
            ], now()->addHours(ProcessGrmUpload::PROGRESS_CACHE_TTL_HOURS));
        }
    }
}
