<?php

namespace App\Models;

use App\Casts\AsClassName;
use App\Http\Integrations\Discord\Data\Messages\MessagePayloadData;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['type', 'channel_id', 'message_id', 'payload', 'created_by_user_id'])]
#[Hidden(['updated_at', 'deleted_at'])]
class DiscordNotification extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => AsClassName::class,
            'payload' => MessagePayloadData::class,
        ];
    }

    /**
     * Get the user who created this notification.
     *
     * A null value indicates that the notification was created by the system rather than a specific user.
     */
    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * Get the related model entries for this notification.
     */
    public function relatedModels(): HasMany
    {
        return $this->hasMany(DiscordNotificationRelatedModel::class);
    }

    /**
     * Scope to notifications linked to the given model through their related-model rows.
     */
    #[Scope]
    protected function relatedTo(Builder $query, string $modelType, int|string $modelId): void
    {
        $query->whereHas('relatedModels', fn (Builder $related) => $related
            ->where('model_type', $modelType)
            ->where('model_id', (string) $modelId)
        );
    }
}
