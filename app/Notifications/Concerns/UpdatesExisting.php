<?php

namespace App\Notifications\Concerns;

use App\Models\DiscordNotification;

trait UpdatesExisting
{
    /**
     * The notification instance this notification will update, if any.
     *
     * This allows the notification to update an existing Discord message instead of creating a new one,
     * which is useful for notifications related to the same event or model that may be updated multiple times.
     */
    public ?DiscordNotification $updates = null;

    /**
     * Get the notification instance this notification will update, if any.
     */
    public function updates(): ?DiscordNotification
    {
        return $this->updates;
    }

    /**
     * Set the notification instance this notification will update.
     */
    public function updatesExisting(?DiscordNotification $notification): self
    {
        $this->updates = $notification;

        return $this;
    }
}
