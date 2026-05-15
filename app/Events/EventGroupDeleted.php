<?php

namespace App\Events;

use App\Contracts\Events\FlushesRaidingCache;
use Illuminate\Foundation\Events\Dispatchable;

class EventGroupDeleted implements FlushesRaidingCache
{
    use Dispatchable;

    public function __construct(
        public readonly int $groupId,
        public readonly string $eventId,
    ) {}
}
