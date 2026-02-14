<?php

namespace App\Notifications;

use App\Models\LootCouncil\Comment;
use App\Services\Blizzard\ItemService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;
use NotificationChannels\Discord\DiscordChannel;
use NotificationChannels\Discord\DiscordMessage;

class NewLootCouncilComment extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Comment $comment,
    ) {}

    /**
     * @return array<int, class-string>
     */
    public function via(object $notifiable): array
    {
        return [DiscordChannel::class];
    }

    /**
     * Get the Discord representation of the notification.
     */
    public function toDiscord(object $notifiable): DiscordMessage
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

        return DiscordMessage::create()
            ->embed([
                'title' => 'New comment received',
                'url' => $itemUrl,
                'color' => 5814783,
                'description' => $description,
                'timestamp' => $this->comment->created_at->toIso8601String(),
            ])
            ->components([
                //     [
                //         'type' => 1, // Action Row
                //         'components' => [
                //             [
                //                 'type' => 2, // Button
                //                 'style' => 3, // Success (green)
                //                 'label' => 'Resolve',
                //                 'custom_id' => "resolve_lc_comment:{$this->comment->id}",
                //             ],
                //         ],
                //     ],
            ]);
    }

    /**
     * Resolve the item name from the Blizzard API.
     */
    protected function resolveItemName(int $itemId): string
    {
        try {
            $data = app(ItemService::class)->find($itemId);

            return $data['name'] ?? "Item #{$itemId}";
        } catch (\Exception) {
            return "Item #{$itemId}";
        }
    }
}
