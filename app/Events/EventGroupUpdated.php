<?php

namespace App\Events;

use App\Contracts\Events\FlushesRaidingCache;
use App\Models\EventAssignmentGroup;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EventGroupUpdated implements FlushesRaidingCache
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly EventAssignmentGroup $group) {}
}
