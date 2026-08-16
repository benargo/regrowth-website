<?php

namespace App\Events\Broadcasts;

use App\Contracts\Commentable;
use App\Http\Resources\CommentReactionResource;
use App\Models\CommentReaction;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CommentReactionChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  'created'|'deleted'  $action
     */
    public function __construct(
        public readonly CommentReaction $reaction,
        public readonly string $action,
    ) {}

    /**
     * Broadcast on the channel resolved from the reacted-to comment's commentable.
     *
     * Returns no channels when the commentable doesn't implement Commentable —
     * see CommentPosted for the reasoning.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        $commentable = $this->reaction->comment?->commentable;

        if (! $commentable instanceof Commentable) {
            return [];
        }

        return [$commentable->commentChannel()];
    }

    public function broadcastAs(): string
    {
        return 'CommentReactionChanged';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $payload = [
            'comment_id' => $this->reaction->comment_id,
            'action' => $this->action,
        ];

        if ($this->action === 'created') {
            $payload['reaction'] = (new CommentReactionResource($this->reaction))->resolve();
        }

        return $payload;
    }
}
