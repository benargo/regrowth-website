<?php

namespace App\Notifications;

use App\Facades\Blizzard;
use App\Http\Integrations\Blizzard\Exceptions\ItemNotFoundException;
use App\Http\Integrations\Blizzard\Requests\Item\GetItemRequest;
use App\Models\LootCouncil\Comment;
use App\Services\Discord\Notifications\Notification;
use App\Services\Discord\Payloads\MessagePayload;
use App\Services\Discord\Resources\Embed;
use Illuminate\Support\Str;

class NewLootCouncilComment extends Notification
{
    public function __construct(Comment $comment)
    {
        $this->withRelatedModels([$comment])
            ->withSender($comment->user);
    }

    /**
     * Get the payload to send to Discord for this notification.
     */
    public function toMessage(): MessagePayload
    {
        /** @var Comment $comment */
        $comment = $this->hydrateOrFail($this->relatedModel(Comment::class));

        $item = $comment->item;
        $user = $comment->user;
        $itemName = $this->resolveItemName($item->id);

        $description = sprintf(
            "New comment posted by <@%s> on **%s**\n\n%s",
            $user->id,
            $itemName,
            $comment->body,
        );

        $itemUrl = route('loot.items.show', [
            'item' => $item->id,
            'name' => Str::slug($itemName),
        ]);

        return MessagePayload::from([
            'embeds' => [Embed::from([
                'title' => 'New comment received',
                'url' => $itemUrl,
                'color' => 5814783,
                'description' => $description,
                'timestamp' => $comment->created_at->toIso8601String(),
            ])],
        ]);
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

    private function resolveItemName(int $itemId): string
    {
        try {
            $item = Blizzard::send(new GetItemRequest($itemId))->dto();

            return $item->name;
        } catch (ItemNotFoundException) {
            return "Item #{$itemId}";
        }
    }
}
