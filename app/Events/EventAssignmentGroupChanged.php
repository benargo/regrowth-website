<?php

namespace App\Events;

use App\Contracts\Events\FlushesRaidingCache;
use App\Models\EventAssignmentGroup;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EventAssignmentGroupChanged implements FlushesRaidingCache, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(public EventAssignmentGroup $eventAssignmentGroup) {}
}
