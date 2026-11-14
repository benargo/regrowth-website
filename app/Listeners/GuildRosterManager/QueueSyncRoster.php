<?php

namespace App\Listeners\GuildRosterManager;

use App\Events\DesktopClient\SyncCommitting;
use App\Jobs\GuildRosterManager\SyncRoster;

class QueueSyncRoster
{
    /**
     * Queue the job that applies the committed sync's changes.
     */
    public function handle(SyncCommitting $event): void
    {
        SyncRoster::dispatch($event->sync->id, $event->sync->game_version_id, $event->changes);
    }
}
