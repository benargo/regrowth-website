<?php

namespace App\Notifications;

use App\Enums\RaidColor;
use App\Models\Event;
use App\Notifications\Concerns\UpdatesExisting;
use App\Services\Discord\Enums\EmbedType;
use App\Services\Discord\Notifications\Driver;
use App\Services\Discord\Notifications\Notification;
use App\Services\Discord\Payloads\MessagePayload;
use App\Services\Discord\Resources\Embed;
use App\Services\Discord\Resources\EmbedFooter;
use App\Services\Discord\Resources\EmbedMedia;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Support\Facades\Storage;

class RaidAssignmentsPublished extends Notification implements ShouldBroadcast
{
    use UpdatesExisting;

    /** The event that triggered the raid assignments to be published. */
    public Event $event;

    public function __construct(Event $event)
    {
        $this->event = $event;

        $this->withRelatedModels([$this->event]);
    }

    /**
     * Get the notification channels. Appends 'broadcast' when a sender is set so the
     * WebSocket confirmation is only sent when there is a publisher to notify.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $channels = [Driver::class];

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

    public function broadcastAs(): string
    {
        return 'AssignmentsPublished';
    }

    /**
     * @return array<string, string>
     */
    public function broadcastWith(): array
    {
        return ['message' => 'Assignments published to Discord successfully.'];
    }

    /**
     * Get the payload to send to Discord for this notification.
     */
    public function toMessage(): MessagePayload
    {
        $raids = $this->event->raids()->pluck('id')->all();
        $color = RaidColor::fromRaidId($raids);

        return MessagePayload::from([
            'embeds' => [new Embed(
                title: 'Assignments posted for tonight!',
                type: EmbedType::Rich,
                description: 'Assignments for tonight have been posted. Please familiarise yourself with your duties this evening.',
                url: route('raiding.plans.show', ['event' => $this->event]),
                color: $color->value,
                footer: $this->embedFooter(),
                image: $this->embedMedia(),
                timestamp: now()->toIso8601String(),
            )],
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

    /**
     * Get the embed footer for the notification, including the sender's nickname and avatar if available.
     */
    protected function embedFooter(): ?EmbedFooter
    {
        if ($this->sender()) {
            return new EmbedFooter(
                text: "Posted by {$this->sender()->nickname}",
                icon_url: $this->sender()->avatarUrl,
            );
        }

        return null;
    }

    /**
     * Get the embed media for the notification, which is a blueprint image related to raid assignments.
     */
    protected function embedMedia(): ?EmbedMedia
    {
        $path = 'images/assignments_blueprint.webp';

        if (! Storage::disk('public')->exists($path)) {
            if (! file_exists(resource_path($path))) {
                return null;
            }

            Storage::disk('public')->put($path, file_get_contents(resource_path($path)));
        }

        return new EmbedMedia(
            url: Storage::disk('public')->url($path),
            content_type: 'image/webp',
            description: 'A blueprint for raid assignments with various notes and markings on it.',
        );
    }
}
