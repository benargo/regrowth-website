<?php

namespace App\Events\DesktopClient;

use App\Models\GuildRosterManager\Upload;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A desktop sync is fully processed. Broadcasts the confirmation to the desktop client that sent it,
 * once any surrounding transaction has committed.
 */
class SyncCommitted implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Upload $sync,
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("desktop-clients.{$this->sync->desktop_client_id}")];
    }

    public function broadcastAs(): string
    {
        return 'SyncCommitted';
    }

    /**
     * @return array{syncId: string, revision: int|null, outcome: string, startedAt: string|null}
     */
    public function broadcastWith(): array
    {
        return [
            'syncId' => $this->sync->id,
            'revision' => $this->sync->revision,
            'outcome' => $this->sync->outcome->value,
            'startedAt' => $this->sync->captured_at?->toIso8601ZuluString(),
        ];
    }
}
