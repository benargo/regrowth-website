<?php

namespace App\Notifications;

use App\Models\Comment;
use App\Models\Item;
use App\Notifications\Concerns\UpdatesExisting;
use App\Services\Discord\Notifications\Notification;
use App\Services\Discord\Payloads\MessagePayload;
use App\Services\Discord\Resources\Embed;
use LogicException;

class NewLootCouncilComment extends Notification
{
    use UpdatesExisting;

    /**
     * The number of live replies to report on the embed.
     */
    protected int $replyCount = 0;

    public function __construct(Comment $comment)
    {
        $this->withRelatedModels([$comment])
            ->withSender($comment->user);
    }

    /**
     * Set the live reply count reported by this notification's embed.
     */
    public function withReplyCount(int $count): self
    {
        $this->replyCount = $count;

        return $this;
    }

    /**
     * Get the payload to send to Discord for this notification.
     *
     * @throws LogicException if the comment's commentable is not an Item.
     */
    public function toMessage(): MessagePayload
    {
        /** @var Comment $comment */
        $comment = $this->hydrateOrFail($this->relatedModel(Comment::class));

        if (! $comment->commentable instanceof Item) {
            throw new LogicException('NewLootCouncilComment only supports Item commentables.');
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

        if ($this->replyCount > 0) {
            $embed['fields'] = [[
                'name' => 'Replies',
                'value' => (string) $this->replyCount,
                'inline' => true,
            ]];
        }

        return MessagePayload::from([
            'embeds' => [Embed::from($embed)],
        ]);
    }

    /**
     * Determine if the notification should be sent.
     */
    public function shouldSend(object $notifiable, string $channel): bool
    {
        /** @var Comment $comment */
        $comment = $this->hydrateOrFail($this->relatedModel(Comment::class));

        return $comment->commentable instanceof Item;
    }

    /**
     * Get the array of data to store in the database for this notification.
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => self::class,
            'channel_id' => $notifiable->channel()->id,
            'payload' => $this->toMessage()->toArray(),
            'created_by_user_id' => $this->sender()?->id,
        ];
    }
}
