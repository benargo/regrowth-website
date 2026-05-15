<?php

namespace App\Events;

use App\Contracts\Events\FlushesRaidingCache;
use Illuminate\Foundation\Events\Dispatchable;

class EventAssignmentDeleted implements FlushesRaidingCache
{
    use Dispatchable;

    public function __construct(
        public readonly int $assignmentId,
        public readonly string $eventId,
    ) {}
}
