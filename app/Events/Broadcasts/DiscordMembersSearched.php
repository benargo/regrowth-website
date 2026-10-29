<?php

namespace App\Events\Broadcasts;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Tells the officer who searched that SearchGuildMembers has cached results for
 * this query and limit, or has failed for good. Carries no member data; the
 * page reloads the `discord_members` prop to read the cached results.
 */
class DiscordMembersSearched implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public readonly string $userId,
        public readonly string $query,
        public readonly int $limit,
        public readonly bool $failed = false,
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
        return 'DiscordMembersSearched';
    }

    /**
     * @return array{query: string, limit: int, failed: bool}
     */
    public function broadcastWith(): array
    {
        return ['query' => $this->query, 'limit' => $this->limit, 'failed' => $this->failed];
    }
}
