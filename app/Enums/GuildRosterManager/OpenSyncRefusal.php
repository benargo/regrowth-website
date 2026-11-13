<?php

namespace App\Enums\GuildRosterManager;

enum OpenSyncRefusal: string
{
    case Duplicate = 'duplicate';
    case TicketExpired = 'ticket_expired';
    case RevisionConflict = 'revision_conflict';
}
