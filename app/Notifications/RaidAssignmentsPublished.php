<?php

namespace App\Notifications;

use App\Enums\RaidColor;
use App\Models\DiscordNotification;
use App\Models\Event;
use App\Notifications\Concerns\UpdatesExisting;
use App\Services\Discord\Enums\EmbedType;
use App\Services\Discord\Notifications\Notification;
use App\Services\Discord\Payloads\MessagePayload;
use App\Services\Discord\Resources\Embed;
use App\Services\Discord\Resources\EmbedFooter;
use App\Services\Discord\Resources\EmbedMedia;
use Illuminate\Support\Facades\Storage;

class RaidAssignmentsPublished extends Notification
{
    use UpdatesExisting;

    /**
     * The event that triggered the raid assignments to be published, used to include relevant information in the
     * notification message and relationships.
     */
    private Event $event;

    public function __construct(Event $event)
    {
        $this->event = $event;

        $this->withRelatedModels([$this->event]);

        // Check for existing notifications for this event that we should update instead of creating a new one
        $existingNotification = DiscordNotification::where('type', self::class)
            ->whereJsonContains('related_models->App\\\\Models\\\\Event', $this->event->getKey())
            ->latest()
            ->first();

        if ($existingNotification) {
            $this->updatesExisting($existingNotification);
        }
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
                url: route('raiding.plans.show', ['event' => $this->event->id]),
                color: $color->value,
                footer: $this->embedFooter(),
                image: $this->embedMedia(),
                timestamp: now()->toIso8601String(),
            )],
        ]);
    }

    /**
     * Get the embed footer for the notification, including the sender's nickname and avatar if available.
     */
    private function embedFooter(): ?EmbedFooter
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
    private function embedMedia(): ?EmbedMedia
    {
        $path = 'images/assignments_blueprint.webp';
        $contentType = 'image/webp';
        $description = 'A blueprint for raid assignments with various notes and markings on it.';

        // First check if the image exists in the storage
        if (Storage::exists($path)) {
            return new EmbedMedia(
                url: Storage::url($path),
                content_type: $contentType,
                description: $description,
            );
        }

        // If not found in storage, check if it exists in the resources directory.
        if (file_exists(resource_path($path))) {
            Storage::put($path, file_get_contents(resource_path($path)));

            return new EmbedMedia(
                url: Storage::url($path),
                content_type: $contentType,
                description: $description,
            );
        }

        // If the image is not found in either location, return null to omit the embed media from the notification.
        return null;
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
            'related_models' => $this->mapRelatedModels(),
            'created_by_user_id' => $this->sender()?->id,
        ];
    }
}
