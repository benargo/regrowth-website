<?php

namespace App\Events;

use App\Contracts\Events\FlushesRaidingCache;
use App\Models\Event;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EventAssignmentsReordered implements FlushesRaidingCache
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly Event $event) {}
}
