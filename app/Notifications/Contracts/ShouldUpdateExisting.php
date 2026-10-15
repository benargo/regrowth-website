<?php

namespace App\Notifications\Contracts;

use App\Http\Integrations\Discord\Contracts\Channel;
use App\Models\DiscordNotification;

/**
 * A Discord notification that edits its previously posted message instead of posting a new one.
 */
interface ShouldUpdateExisting
{
    /**
     * Get the stored notification this notification edits in the given channel, if any.
     */
    public function existingNotification(Channel $notifiable): ?DiscordNotification;
}
