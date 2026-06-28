<?php

namespace App\Events\Broadcasts;

use App\Http\Resources\LootCouncil\PriorityResource;
use App\Models\Item;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ItemUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly Item $item) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [new Channel("item.{$this->item->id}")];
    }

    public function broadcastAs(): string
    {
        return 'ItemUpdated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'notes' => $this->item->notes,
            'priorities' => PriorityResource::collection($this->item->priorities)->resolve(),
        ];
    }
}
