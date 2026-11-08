<?php

namespace App\Events\Broadcasts;

use Carbon\CarbonInterface;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class RaceStandingsUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public readonly CarbonInterface $checkedAt,
        public readonly bool $hasChanges,
    ) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [new Channel('race-to-60')];
    }

    public function broadcastAs(): string
    {
        return 'RaceStandingsUpdated';
    }

    /**
     * @return array{checkedAt: string, hasChanges: bool}
     */
    public function broadcastWith(): array
    {
        return [
            'checkedAt' => $this->checkedAt->toIso8601String(),
            'hasChanges' => $this->hasChanges,
        ];
    }
}
