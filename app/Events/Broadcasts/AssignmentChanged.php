<?php

namespace App\Events\Broadcasts;

use App\Contracts\Events\FlushesRaidingCache;
use App\Models\Event;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Queue\SerializesModels;

class AssignmentChanged implements FlushesRaidingCache, ShouldBroadcast
{
    use InteractsWithSockets, SerializesModels;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public readonly string $eventId,
        public readonly string $action,
        public readonly array $payload,
    ) {}

    /**
     * @param  array<int, int>  $orderedIds
     */
    public static function forReorder(Event $event, array $orderedIds): static
    {
        return new static($event->id, 'reordered', ['order' => $orderedIds]);
    }

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("event.{$this->eventId}")];
    }

    public function broadcastAs(): string
    {
        return 'AssignmentChanged';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return ['action' => $this->action, ...$this->payload];
    }
}
