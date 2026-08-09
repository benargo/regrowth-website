<?php

namespace App\Jobs;

use App\Models\Comment;
use App\Models\DiscordNotification;
use App\Notifications\NewLootCouncilComment;
use App\Services\Discord\Discord;
use App\Services\Discord\Notifications\NotifiableChannel;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Log;

class SyncCommentReplyCount implements ShouldQueue
{
    use Queueable;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 30;

    public function __construct(
        public Comment $root,
    ) {}

    /**
     * Prevent two syncs for the same thread from racing each other's Discord edit.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("comment-reply-count-sync:{$this->root->getKey()}"))->dontRelease(),
        ];
    }

    /**
     * Execute the job: re-send the root's Discord notification with its live
     * reply count.
     *
     * No-ops when the root has no DiscordNotification record — the comment
     * predates this feature, or its message was deleted. This must never post
     * a fresh message for a comment whose original notification is gone.
     */
    public function handle(Discord $discord): void
    {
        $record = $this->existingNotification();

        if ($record === null) {
            return;
        }

        NotifiableChannel::fromConfig('lootcouncil', $discord)->notify(
            (new NewLootCouncilComment($this->root))
                ->withReplyCount($this->countFor($this->root))
                ->updatesExisting($record),
        );
    }

    /**
     * Count the root's live (non-trashed) replies.
     */
    public function countFor(Comment $root): int
    {
        return $root->replies()->count();
    }

    /**
     * Find the Discord message record posted for this root, if any.
     */
    protected function existingNotification(): ?DiscordNotification
    {
        return DiscordNotification::where('type', NewLootCouncilComment::class)
            ->whereHas('relatedModels', fn ($query) => $query
                ->where('model_type', Comment::class)
                ->where('model_id', $this->root->getKey())
            )
            ->latest()
            ->first();
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('SyncCommentReplyCount failed.', [
            'comment_id' => $this->root->getKey(),
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
