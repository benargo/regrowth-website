<?php

namespace App\Models;

use App\Casts\AsNotificationType;
use App\Casts\AsRelationshipIndex;
use App\Services\Discord\Payloads\MessagePayload;
use Database\Factories\DiscordNotificationFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

#[UseFactory(DiscordNotificationFactory::class)]
class DiscordNotification extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that should be cast to native types.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'type' => AsNotificationType::class,
        'payload' => MessagePayload::class,
        'related_models' => AsRelationshipIndex::class,
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'type',
        'channel_id',
        'message_id',
        'payload',
        'related_models',
        'created_by_user_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'updated_at',
        'deleted_at',
    ];

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
     * Get the related models for this notification.
     *
     * @return Collection<string, Model>
     */
    public function relatedModels(): Collection
    {
        return $this->related_models;
    }
}
