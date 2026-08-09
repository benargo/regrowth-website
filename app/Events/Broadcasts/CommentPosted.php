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

class CommentPosted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly Comment $comment) {}

    /**
     * Broadcast on the channel resolved from the comment's commentable.
     *
     * Returns no channels when the commentable doesn't implement Commentable.
     * Returning an empty array is how this event declines to broadcast rather
     * than fabricating a channel for a subject with no defined channel.
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
        return 'CommentPosted';
    }

    /**
     * Get the data to broadcast.
     *
     * `parent_id` is lifted to the top level so the client can route an
     * incoming reply into its thread without unpacking the resource.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'comment' => (new CommentResource($this->comment))->resolve(),
            'parent_id' => $this->comment->parent_id,
        ];
    }
}
