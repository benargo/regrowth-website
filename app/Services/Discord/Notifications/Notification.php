<?php

namespace App\Services\Discord\Notifications;

use App\Services\Discord\Payloads\MessagePayload;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notification as BaseNotification;

abstract class Notification extends BaseNotification implements ShouldQueue
{
    use Queueable;

    /**
     * The related models to store in the database, if any.
     *
     * @var array<string, Model>|null
     */
    public ?array $relatedModels = null;

    /**
     * The user who sent this notification, if any.
     */
    public ?Authenticatable $sender = null;

    /**
     * Get the notification channels.
     *
     * @return array<int, string> The notification channels to send the message through (e.g. a custom DiscordChannel class)
     */
    public function via(object $notifiable): array
    {
        return [Driver::class];
    }

    /**
     * Get the payload to send to Discord for this notification.
     */
    abstract public function toMessage(): MessagePayload;

    /**
     * Get the array of data to store in the database for this notification.
     */
    abstract public function toDatabase(object $notifiable): array;

    /**
     * Map the related models for this notification.
     */
    public function mapRelatedModels(): array
    {
        if (is_null($this->relatedModels)) {
            return [];
        }

        return collect($this->relatedModels)
            ->groupBy(fn ($model) => get_class($model))
            ->mapWithKeys(fn ($group, $modelClass) => [
                $modelClass => $group->map(fn ($model) => $model->getKey())->values()->all(),
            ])
            ->all();
    }

    /**
     * Set the related models for this notification.
     */
    public function withRelatedModels(iterable $related): self
    {
        $this->relatedModels = (array) $related;

        return $this;
    }

    /**
     * Get the user who sent this notification.
     */
    public function sender(): ?Authenticatable
    {
        return $this->sender;
    }

    /**
     * Set the user who sent this notification.
     */
    public function withSender(Authenticatable $user): self
    {
        $this->sender = $user;

        return $this;
    }
}
