<?php

namespace App\Data\GuildRosterManager;

use App\Enums\GuildRosterManager\CommitSyncStatus;
use App\Models\GuildRosterManager\Upload;
use Spatie\LaravelData\Data;

/**
 * The result of committing a sync: its status, the sync and a roster revision number.
 */
final class CommitSyncResultData extends Data
{
    /**
     * @param  int  $revision  The sync's revision when committed, otherwise the game version's current revision.
     */
    public function __construct(
        public readonly CommitSyncStatus $status,
        public readonly Upload $sync,
        public readonly int $revision,
    ) {}
}
