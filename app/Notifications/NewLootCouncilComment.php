<?php

namespace App\Notifications;

use App\Models\LootCouncil\Comment;
use App\Services\Blizzard\BlizzardService;
use App\Services\Discord\Notifications\Notification;
use App\Services\Discord\Payloads\MessagePayload;
use App\Services\Discord\Resources\Embed;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Str;

class NewLootCouncilComment extends Notification
{
    public function __construct(
        public Comment $comment,
    ) {
        $this->withRelatedModels([$comment]);
    }

    public function toMessage(): MessagePayload
    {
        $item = $this->comment->item;
        $user = $this->comment->user;
        $itemName = $this->resolveItemName($item->id);

        $description = sprintf(
            "New comment posted by <@%s> on **%s**\n\n%s",
            $user->id,
            $itemName,
            $this->comment->body,
        );

        $itemUrl = route('loot.items.show', [
            'item' => $item->id,
            'name' => Str::slug($itemName),
        ]);

        return MessagePayload::from([
            'embeds' => [new Embed(
                title: 'New comment received',
                url: $itemUrl,
                color: 5814783,
                description: $description,
                timestamp: $this->comment->created_at->toIso8601String(),
            )],
        ]);
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => self::class,
            'channel_id' => $notifiable->channel()->id,
            'payload' => $this->toMessage()->toArray(),
            'related_models' => $this->mapRelatedModels(),
            'created_by_user_id' => $this->sender()?->id,
        ];
    }

    public function sender(): ?Authenticatable
    {
        return $this->comment->user;
    }

    /**
     * Resolve the item name from the Blizzard API.
     */
    protected function resolveItemName(int $itemId): string
    {
        try {
            $data = app(BlizzardService::class)->findItem($itemId);

            return $data['name'] ?? "Item #{$itemId}";
        } catch (\Exception) {
            return "Item #{$itemId}";
        }
    }
}
