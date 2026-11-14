<?php

namespace App\Enums\GuildRosterManager;

enum CommitSyncStatus: string
{
    case Committed = 'committed';
    case RevisionConflict = 'revision_conflict';
    case TicketExpired = 'ticket_expired';
    case Expired = 'expired';
    case Abandoned = 'abandoned';
}
