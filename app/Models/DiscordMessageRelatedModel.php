<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[WithoutTimestamps]
#[Fillable(['discord_message_id', 'model_type', 'model_id'])]
class DiscordMessageRelatedModel extends Model
{
    /**
     * Get the DiscordMessage that this related model belongs to.
     *
     * @return BelongsTo<DiscordMessage, $this>
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(DiscordMessage::class, 'discord_message_id');
    }

    /**
     * Get the related model (of any type) that is associated with this record.
     */
    public function relatedModel(): MorphTo
    {
        return $this->morphTo('model');
    }
}
