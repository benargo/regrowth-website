<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiscordNotificationRelatedModel extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'discord_notification_id',
        'model_type',
        'model_id',
    ];

    public function discordNotification(): BelongsTo
    {
        return $this->belongsTo(DiscordNotification::class);
    }
}
