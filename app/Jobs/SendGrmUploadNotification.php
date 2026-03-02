<?php

namespace App\Jobs;

use App\Notifications\DiscordNotifiable;
use App\Notifications\GrmUploadCompleted;
use App\Notifications\GrmUploadFailed;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

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
        } else {
            DiscordNotifiable::officer()->notify(
                new GrmUploadCompleted($this->processedCount, $this->skippedCount, $this->warningCount)
            );
        }
    }
}
