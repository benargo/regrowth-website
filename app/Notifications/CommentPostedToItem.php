<?php

namespace App\Notifications;

use App\Http\Integrations\Discord\Contracts\Channel;
use App\Http\Integrations\Discord\Data\Messages\EmbedData;
use App\Http\Integrations\Discord\Data\Messages\MessagePayloadData;
use App\Models\Comment;
use App\Models\Item;
use App\Notifications\Concerns\UpdatesExisting;
use App\Notifications\Contracts\ShouldUpdateExisting;
use App\Notifications\Discord\DiscordMessage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Log;
use LogicException;
use Throwable;

class CommentPostedToItem extends DiscordMessage implements ShouldUpdateExisting
{
    use UpdatesExisting;

    /**
     * Whether this notification only edits the thread's posted message, sending nothing when there is none.
     */
    protected bool $onlyUpdatesExisting = false;

    public function __construct(Comment $comment)
    {
        $this->withRelatedModels([$comment])
            ->withSender($comment->user);
    }

    /**
     * Create a notification that edits the root comment's posted message with its live reply count.
     */
    public static function refresh(Comment $root): self
    {
        $notification = new self($root);
        $notification->onlyUpdatesExisting = true;

        return $notification;
    }

    /**
     * Get the job middleware the queued notification passes through: one refresh per thread at a time,
     * released while Discord is rate limited.
     *
     * @return array<int, object>
     */
    public function middleware(object $notifiable, string $channel): array
    {
        if (! $this->onlyUpdatesExisting) {
            return parent::middleware($notifiable, $channel);
        }

        return [
            (new WithoutOverlapping("comment-thread:{$this->relatedModel(Comment::class)['model_id']}"))->dontRelease(),
            ...parent::middleware($notifiable, $channel),
        ];
    }

    /**
     * Get the payload to send to Discord for this notification.
     *
     * @throws LogicException if the comment's commentable is not an Item.
     */
    public function toMessage(): MessagePayloadData
    {
        $comment = $this->comment();

        if (! $comment->commentable instanceof Item) {
            throw new LogicException('CommentPostedToItem only supports Item commentables.');
        }

        $item = $comment->commentable;
        $user = $comment->user;

        $description = sprintf(
            "New comment posted by <@%s> on **%s**\n\n%s",
            $user->id,
            $item->name,
            $comment->body,
        );

        $itemUrl = route('loot.items.show', [
            'item' => $item->id,
            'slug' => $item->slug,
        ]);

        $embed = [
            'title' => 'New comment received',
            'url' => $itemUrl,
            'color' => 5814783,
            'description' => $description,
            'timestamp' => $comment->created_at->toIso8601String(),
        ];

        $replyCount = $comment->replies()->count();

        if ($replyCount > 0) {
            $embed['fields'] = [[
                'name' => 'Replies',
                'value' => (string) $replyCount,
                'inline' => true,
            ]];
        }

        return MessagePayloadData::from([
            'embeds' => [EmbedData::from($embed)],
        ]);
    }

    /**
     * Determine if the notification should be sent: the comment must be on an Item, and a
     * refresh needs a posted message to edit.
     */
    public function shouldSend(object $notifiable, string $channel): bool
    {
        if (! $this->comment()->commentable instanceof Item) {
            return false;
        }

        return ! $this->onlyUpdatesExisting || $this->existingNotification($notifiable) !== null;
    }

    /**
     * Get the array of data to store in the database for this notification.
     */
    public function toDatabase(Channel $notifiable): array
    {
        return [
            'type' => self::class,
            'channel_id' => $notifiable->id,
            'payload' => $this->toMessage()->toArray(),
            'created_by_user_id' => $this->sender()?->id,
        ];
    }

    /**
     * Log a failed send with the comment's id.
     */
    public function failed(Throwable $exception): void
    {
        Log::error('CommentPostedToItem failed.', [
            'comment_id' => $this->relatedModel(Comment::class)['model_id'],
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }

    /**
     * Narrow the stored-notification lookup to this notification's comment.
     */
    protected function constrainExisting(Builder $query): Builder
    {
        return $query->relatedTo(Comment::class, $this->relatedModel(Comment::class)['model_id']);
    }

    /**
     * Get this notification's comment, including a soft-deleted one.
     */
    protected function comment(): Comment
    {
        return Comment::withTrashed()->findOrFail($this->relatedModel(Comment::class)['model_id']);
    }
}
