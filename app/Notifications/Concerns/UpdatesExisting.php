<?php

namespace App\Notifications\Concerns;

use App\Models\DiscordChannel;
use App\Models\DiscordMessage;
use Illuminate\Database\Eloquent\Builder;

/**
 * Finds the stored Discord message a notification edits, when the notification is sent.
 */
trait UpdatesExisting
{
    /**
     * The stored message found by the lookup.
     */
    private ?DiscordMessage $existingMessage = null;

    /**
     * Whether the lookup has run.
     */
    private bool $hasResolvedExistingMessage = false;

    /**
     * Get the latest stored message of this type in the given channel that matches
     * constrainExisting(), if any. The lookup runs on first call and the result is reused.
     */
    public function existingMessage(DiscordChannel $notifiable): ?DiscordMessage
    {
        if (! $this->hasResolvedExistingMessage) {
            $this->existingMessage = $this->constrainExisting(
                DiscordMessage::where('type', static::class)
                    ->where('channel_id', $notifiable->id),
            )->latest()->first();

            $this->hasResolvedExistingMessage = true;
        }

        return $this->existingMessage;
    }

    /**
     * Narrow the query for the stored message this notification edits.
     *
     * @param  Builder<DiscordMessage>  $query
     * @return Builder<DiscordMessage>
     */
    abstract protected function constrainExisting(Builder $query): Builder;
}
