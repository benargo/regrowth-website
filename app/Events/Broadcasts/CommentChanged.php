<?php

namespace App\Events\Broadcasts;

use App\Contracts\Commentable;
use App\Http\Resources\CommentResource;
use App\Models\Comment;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CommentChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly Comment $comment) {}

    /**
     * Broadcast on the channel resolved from the comment's commentable.
     *
     * Returns no channels when the commentable doesn't implement Commentable —
     * see CommentPosted for the reasoning. This event covers both a body edit and
     * a resolve toggle; both are in-place updates to the same comment row after
     * Phase 3, so a single event carrying the full updated resource serves both.
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
        return 'CommentChanged';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'comment' => (new CommentResource($this->comment))->resolve(),
        ];
    }
}
