<?php

namespace App\Notifications\Concerns;

use App\Http\Integrations\Discord\Contracts\Channel;
use App\Models\DiscordNotification;
use Illuminate\Database\Eloquent\Builder;

/**
 * Finds the stored Discord message a notification edits, when the notification is sent.
 */
trait UpdatesExisting
{
    /**
     * The stored notification found by the lookup.
     */
    private ?DiscordNotification $existingNotification = null;

    /**
     * Whether the lookup has run.
     */
    private bool $hasResolvedExistingNotification = false;

    /**
     * Get the latest stored notification of this type in the given channel that matches
     * constrainExisting(), if any. The lookup runs on first call and the result is reused.
     */
    public function existingNotification(Channel $notifiable): ?DiscordNotification
    {
        if (! $this->hasResolvedExistingNotification) {
            $this->existingNotification = $this->constrainExisting(
                DiscordNotification::where('type', static::class)
                    ->where('channel_id', $notifiable->id),
            )->latest()->first();

            $this->hasResolvedExistingNotification = true;
        }

        return $this->existingNotification;
    }

    /**
     * Narrow the query for the stored notification this notification edits.
     *
     * @param  Builder<DiscordNotification>  $query
     * @return Builder<DiscordNotification>
     */
    abstract protected function constrainExisting(Builder $query): Builder;
}
