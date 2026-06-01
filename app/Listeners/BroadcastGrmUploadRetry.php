<?php

namespace App\Listeners;

use App\Events\Broadcasts\GrmUploadRetrying;
use App\Jobs\ProcessGrmUpload;
use Illuminate\Queue\Events\JobReleasedAfterException;

class BroadcastGrmUploadRetry
{
    /**
     * Broadcast a retry notice when a GRM upload job is released back onto the
     * queue after throwing on a non-final attempt.
     */
    public function handle(JobReleasedAfterException $event): void
    {
        if ($event->job->resolveName() !== ProcessGrmUpload::class) {
            return;
        }

        $command = unserialize($event->job->payload()['data']['command']);

        if (! $command instanceof ProcessGrmUpload) {
            return;
        }

        GrmUploadRetrying::dispatch(
            $command->userId,
            $event->job->attempts(),
            $event->job->maxTries(),
            $event->backoff ?? 0,
        );
    }
}
