<?php

namespace App\Events\Broadcasts;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GrmUploadCompleted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly string $userId,
        public readonly int $processedCount,
        public readonly int $skippedCount,
        public readonly int $warningCount,
        public readonly int $errorCount,
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("App.Models.User.{$this->userId}")];
    }

    public function broadcastAs(): string
    {
        return 'GrmUploadCompleted';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        // Counts only — the full error detail is delivered via the Discord
        // notification, which has no per-message size cap. Sending the unbounded
        // errors array here can exceed Reverb's max_message_size (10 KB).
        return [
            'processedCount' => $this->processedCount,
            'skippedCount' => $this->skippedCount,
            'warningCount' => $this->warningCount,
            'errorCount' => $this->errorCount,
        ];
    }
}
