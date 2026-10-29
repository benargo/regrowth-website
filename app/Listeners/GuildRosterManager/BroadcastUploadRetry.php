<?php

namespace App\Listeners\GuildRosterManager;

use App\Events\Broadcasts\GrmUploadRetrying;
use App\Jobs\GuildRosterManager\ProcessUpload;
use Illuminate\Queue\Events\JobReleasedAfterException;

class BroadcastUploadRetry
{
    /**
     * Broadcast a retry notice when a GRM upload job is released back onto the
     * queue after throwing on a non-final attempt.
     */
    public function handle(JobReleasedAfterException $event): void
    {
        if ($event->job->resolveName() !== ProcessUpload::class) {
            return;
        }

        $command = unserialize($event->job->payload()['data']['command']);

        if (! $command instanceof ProcessUpload) {
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
