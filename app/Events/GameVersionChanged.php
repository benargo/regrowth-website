<?php

namespace App\Events;

use App\Contracts\Events\FlushesGameVersionsCache;
use App\Models\GameVersion;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GameVersionChanged implements FlushesGameVersionsCache, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(public GameVersion $gameVersion) {}
}
