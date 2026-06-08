<?php

namespace App\Services\Discord\Notifications;

use App\Services\Discord\Payloads\MessagePayload;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Notifications\Notification as BaseNotification;

abstract class Notification extends BaseNotification implements ShouldQueue
{
    use Queueable;

    /**
     * The related model references to store in the database, if any.
     *
     * Stored as lightweight identifier arrays rather than full models so the
     * notification stays JSON-encodable when queued, even if a related model
     * carries binary (non-UTF-8) attributes such as a varbinary color column.
     *
     * @var list<array{model_id: int|string, model_type: class-string<Model>}>|null
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
     * Models are reduced to lightweight identifier arrays immediately so the
     * notification never holds a full model (and its potentially binary
     * attributes) once queued.
     *
     * @param  iterable<Model>  $related
     */
    public function withRelatedModels(iterable $related): self
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
     * Notifications store related models as lightweight identifier arrays so the
     * queue payload stays JSON-encodable. Use this to resolve a reference back
     * into its model when the full record is needed (e.g. while building the
     * message). Returns null for a null reference or a missing record.
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
     * Use this when the message cannot be built without the related record, to
     * preserve the loud-failure behaviour of Laravel's model serialization.
     *
     * @param  array{model_id: int|string, model_type: class-string<Model>}|null  $reference
     *
     * @throws ModelNotFoundException
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
    public function withSender(Authenticatable $user): self
    {
        $this->sender = $user;

        return $this;
    }
}
