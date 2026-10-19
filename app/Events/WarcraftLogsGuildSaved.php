<?php

namespace App\Events;

use App\Contracts\Events\FlushesGameVersionsCache;
use App\Models\WarcraftLogs\Guild;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WarcraftLogsGuildSaved implements FlushesGameVersionsCache
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(public Guild $guild) {}
}
