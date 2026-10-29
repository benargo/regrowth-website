<?php

namespace App\Notifications\Discord;

use App\Http\Integrations\Discord\Data\Messages\MessagePayloadData;
use App\Jobs\Middleware\ReleaseWhenDiscordRateLimited;
use App\Models\DiscordChannel;
use DateTimeInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Notifications\Notification as BaseNotification;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\MaxExceptions;

/**
 * Base class for queued notifications that post a message to Discord through the DiscordNotificationChannel.
 */
#[MaxExceptions(3)]
#[Backoff(30)]
abstract class DiscordNotification extends BaseNotification implements ShouldQueue
{
    use Queueable;

    /**
     * The related model references to store in the database, if any.
     *
     * @var list<array{model_id: int|string, model_type: class-string<Model>}>|null
     */
    public ?array $relatedModels = null;

    /**
     * The user who sent this notification, if any.
     */
    public ?Authenticatable $sender = null;

    /**
     * Get the time after which the queued notification stops being retried.
     */
    public function retryUntil(): DateTimeInterface
    {
        return now()->addHour();
    }

    /**
     * Get the job middleware the queued notification passes through.
     *
     * @return array<int, object>
     */
    public function middleware(object $notifiable, string $channel): array
    {
        return [new ReleaseWhenDiscordRateLimited];
    }

    /**
     * Get the notification channels.
     *
     * @return array<int, class-string>
     */
    public function via(object $notifiable): array
    {
        return [DiscordNotificationChannel::class];
    }

    /**
     * Get the payload to send to Discord for this notification.
     */
    abstract public function toMessage(): MessagePayloadData;

    /**
     * Get the array of data to store in the database for this notification, using the given payload when provided.
     *
     * @return array{type: class-string<static>, channel_id: string, payload: array<string, mixed>, created_by_user_id: int|string|null}
     */
    public function toDatabase(DiscordChannel $notifiable, ?MessagePayloadData $payload = null): array
    {
        return [
            'type' => static::class,
            'channel_id' => $notifiable->id,
            'payload' => ($payload ?? $this->toMessage())->toArray(),
            'created_by_user_id' => $this->sender()?->id,
        ];
    }

    /**
     * Map the related models for this notification.
     *
     * @return list<array{model_id: int|string, model_type: class-string<Model>}>
     */
    public function mapRelatedModels(): array
    {
        return $this->relatedModels ?? [];
    }

    /**
     * Set the related models for this notification.
     *
     * @param  iterable<array-key, Model>  $related
     */
    public function withRelatedModels(iterable $related): static
    {
        $this->relatedModels = collect($related)
            ->map(fn (Model $model) => [
                'model_id' => $model->getKey(),
                'model_type' => $model::class,
            ])
            ->values()
            ->all();

        return $this;
    }

    /**
     * Hydrate a model from a stored reference.
     *
     * @param  array{model_id: int|string, model_type: class-string<Model>}|null  $reference
     */
    protected function hydrate(?array $reference): ?Model
    {
        if ($reference === null) {
            return null;
        }

        return $reference['model_type']::find($reference['model_id']);
    }

    /**
     * Hydrate a model from a stored reference, failing if it cannot be resolved.
     *
     * @param  array{model_id: int|string, model_type: class-string<Model>}|null  $reference
     *
     * @throws ModelNotFoundException when the reference is null or the record is missing.
     */
    protected function hydrateOrFail(?array $reference): Model
    {
        $model = $this->hydrate($reference);

        if ($model === null) {
            throw (new ModelNotFoundException)->setModel($reference['model_type'] ?? Model::class);
        }

        return $model;
    }

    /**
     * Get the first stored related-model reference matching the given type.
     *
     * @param  class-string<Model>  $type
     * @return array{model_id: int|string, model_type: class-string<Model>}|null
     */
    protected function relatedModel(string $type): ?array
    {
        return collect($this->relatedModels ?? [])
            ->first(fn (array $reference) => $reference['model_type'] === $type);
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
    public function withSender(Authenticatable $user): static
    {
        $this->sender = $user;

        return $this;
    }
}
