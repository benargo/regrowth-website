<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[WithoutTimestamps]
#[Fillable(['discord_notification_id', 'model_type', 'model_id'])]
class DiscordNotificationRelatedModel extends Model
{
    /**
     * Get the DiscordNotification that this related model belongs to.
     */
    public function notification(): BelongsTo
    {
        return $this->belongsTo(DiscordNotification::class, 'discord_notification_id');
    }

    /**
     * Get the related model (of any type) that is associated with this record.
     */
    public function relatedModel(): MorphTo
    {
        return $this->morphTo('model');
    }
}
