<?php

namespace App\Events\Broadcasts;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Tells the officer who picked a Discord member how ResolveGuildMember finished.
 *
 * The status is one of:
 * - `ready`: the member has a users row.
 * - `not_in_guild`: Discord reports the member is not in the server.
 * - `failed`: the resolution failed for good.
 */
class DiscordMemberResolved implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    /**
     * @param  'ready'|'not_in_guild'|'failed'  $status
     */
    public function __construct(
        public readonly string $userId,
        public readonly string $memberId,
        public readonly string $status,
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
        return 'DiscordMemberResolved';
    }

    /**
     * @return array{memberId: string, status: string}
     */
    public function broadcastWith(): array
    {
        return ['memberId' => $this->memberId, 'status' => $this->status];
    }
}
