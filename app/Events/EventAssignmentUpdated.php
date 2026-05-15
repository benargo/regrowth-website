<?php

namespace App\Events;

use App\Contracts\Events\FlushesRaidingCache;
use App\Models\EventAssignment;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EventAssignmentUpdated implements FlushesRaidingCache
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly EventAssignment $assignment) {}
}
