<?php

namespace App\Notifications\Contracts;

use App\Models\DiscordChannel;
use App\Models\DiscordMessage;

/**
 * A Discord notification that edits its previously posted message instead of posting a new one.
 */
interface ShouldUpdateExisting
{
    /**
     * Get the stored message this notification edits in the given channel, if any.
     */
    public function existingMessage(DiscordChannel $notifiable): ?DiscordMessage;
}
