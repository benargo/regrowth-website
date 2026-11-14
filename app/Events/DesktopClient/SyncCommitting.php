<?php

namespace App\Events\DesktopClient;

use App\Data\GuildRosterManager\SyncChangesData;
use App\Models\GuildRosterManager\Upload;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A desktop sync's commit applied changes to the roster state. Dispatched once the commit transaction has committed.
 */
class SyncCommitting implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Upload $sync,
        public SyncChangesData $changes,
    ) {}
}
