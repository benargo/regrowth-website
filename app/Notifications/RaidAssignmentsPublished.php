<?php

namespace App\Notifications;

use App\Http\Integrations\Discord\Contracts\Channel;
use App\Http\Integrations\Discord\Data\Messages\EmbedData;
use App\Http\Integrations\Discord\Data\Messages\EmbedFooterData;
use App\Http\Integrations\Discord\Data\Messages\EmbedMediaData;
use App\Http\Integrations\Discord\Data\Messages\MessagePayloadData;
use App\Http\Integrations\Discord\Enums\EmbedType;
use App\Models\Event;
use App\Notifications\Concerns\UpdatesExisting;
use App\Notifications\Contracts\ShouldUpdateExisting;
use App\Notifications\Discord\DiscordChannel;
use App\Notifications\Discord\DiscordMessage;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Support\Facades\Storage;

class RaidAssignmentsPublished extends DiscordMessage implements ShouldBroadcast, ShouldUpdateExisting
{
    use UpdatesExisting;

    public function __construct(Event $event)
    {
        $this->withRelatedModels([$event]);
    }

    /**
     * Get the notification channels. Appends 'broadcast' when a sender is set so the
     * WebSocket confirmation is only sent when there is a publisher to notify.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $channels = [DiscordChannel::class];

        if ($this->sender() !== null) {
            $channels[] = 'broadcast';
        }

        return $channels;
    }

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("App.Models.User.{$this->sender()->id}")];
    }

    public function broadcastType(): string
    {
        return 'AssignmentsPublished';
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage(['message' => 'Assignments published to Discord successfully.']);
    }

    /**
     * Get the payload to send to Discord for this notification.
     */
    public function toMessage(): MessagePayloadData
    {
        /** @var Event $event */
        $event = $this->hydrateOrFail($this->relatedModel(Event::class));

        return MessagePayloadData::from([
            'embeds' => [EmbedData::from([
                'title' => 'Assignments posted for tonight!',
                'type' => EmbedType::Rich,
                'description' => 'Assignments for tonight have been posted. Please familiarise yourself with your duties this evening.',
                'url' => route('raiding.plans.show', ['event' => $event]),
                'color' => $event->color ? hexdec($event->color) : 7768390,
                'footer' => $this->embedFooter(),
                'image' => $this->embedMedia(),
                'timestamp' => now()->toIso8601String(),
            ])],
        ]);
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
     * Narrow the stored-notification lookup to this notification's event.
     */
    protected function constrainExisting(Builder $query): Builder
    {
        return $query->relatedTo(Event::class, $this->relatedModel(Event::class)['model_id']);
    }

    /**
     * Get the embed footer for the notification, including the sender's nickname and avatar if available.
     */
    protected function embedFooter(): ?EmbedFooterData
    {
        if ($this->sender()) {
            return EmbedFooterData::from([
                'text' => "Posted by {$this->sender()->nickname}",
                'icon_url' => $this->sender()->avatarUrl,
            ]);
        }

        return null;
    }

    /**
     * Get the embed media for the notification, which is a blueprint image related to raid assignments.
     */
    protected function embedMedia(): ?EmbedMediaData
    {
        $path = 'images/assignments_blueprint.webp';

        if (! Storage::disk('public')->exists($path)) {
            if (! file_exists(resource_path($path))) {
                return null;
            }

            Storage::disk('public')->put($path, file_get_contents(resource_path($path)));
        }

        return EmbedMediaData::from([
            'url' => Storage::disk('public')->url($path),
            'content_type' => 'image/webp',
            'description' => 'A blueprint for raid assignments with various notes and markings on it.',
        ]);
    }
}
