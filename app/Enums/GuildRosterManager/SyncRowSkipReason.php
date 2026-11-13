<?php

namespace App\Enums\GuildRosterManager;

enum SyncRowSkipReason: string
{
    case Older = 'older';
    case FutureTime = 'future_time';
    case NoEvidence = 'no_evidence';
    case Forbidden = 'forbidden';
}
