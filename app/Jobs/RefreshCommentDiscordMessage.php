<?php

namespace App\Jobs;

use App\Http\Integrations\Discord\DiscordConnector;
use App\Jobs\Middleware\ReleaseWhenDiscordRateLimited;
use App\Models\Comment;
use App\Models\DiscordNotification;
use App\Notifications\Discord\NotifiableChannel;
use App\Notifications\NewLootCouncilComment;
use DateTimeInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\MaxExceptions;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Log;

#[MaxExceptions(3)]
class RefreshCommentDiscordMessage implements ShouldQueue
{
    use Queueable;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 30;

    public function __construct(
        public Comment $root,
    ) {}

    /**
     * Rate-limit releases (ReleaseWhenDiscordRateLimited) keep the job alive until this deadline.
     */
    public function retryUntil(): DateTimeInterface
    {
        return now()->addHour();
    }

    /**
     * Skip a sync while another for the same thread runs, and release the job on a Discord rate limit.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("comment-reply-count-sync:{$this->root->getKey()}"))->dontRelease(),
            new ReleaseWhenDiscordRateLimited,
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
    public function handle(DiscordConnector $discord): void
    {
        $record = $this->existingNotification();

        if ($record === null) {
            return;
        }

        NotifiableChannel::fromConfig('lootcouncil', $discord)->notify(
            (new NewLootCouncilComment($this->root))
                ->withReplyCount($this->root->replies()->count())
                ->updatesExisting($record),
        );
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
        Log::error('RefreshCommentDiscordMessage failed.', [
            'comment_id' => $this->root->getKey(),
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
