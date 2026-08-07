<?php

namespace App\Events\Broadcasts;

use App\Contracts\Commentable;
use App\Models\Comment;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CommentRemoved implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly Comment $comment) {}

    /**
     * Broadcast on the channel resolved from the comment's commentable.
     *
     * Returns no channels when the commentable doesn't implement Commentable —
     * see CommentPosted for the reasoning.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        $commentable = $this->comment->commentable;

        if (! $commentable instanceof Commentable) {
            return [];
        }

        return [$commentable->commentChannel()];
    }

    public function broadcastAs(): string
    {
        return 'CommentRemoved';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'comment_id' => $this->comment->id,
        ];
    }
}
