<?php

namespace App\Events\Broadcasts;

use App\Contracts\Events\FlushesRaidingCache;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CompositionChanged implements FlushesRaidingCache, ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  array{groups: array<int, mixed>, bench: array<int, mixed>}  $composition
     */
    public function __construct(
        public readonly string $eventId,
        public readonly array $composition,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("event.{$this->eventId}")];
    }

    public function broadcastAs(): string
    {
        return 'CompositionChanged';
    }

    public function broadcastWith(): array
    {
        return [];
    }
}
