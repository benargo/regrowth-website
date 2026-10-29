<?php

namespace App\Actions\Discord;

use App\Http\Integrations\Discord\Exceptions\DiscordRequestException;
use App\Models\DiscordChannel;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Returns the stored Discord channel for an ID, including a trashed one, fetching and storing it first when it is not stored.
 */
class ResolveDiscordChannel
{
    use AsAction;

    /**
     * @throws DiscordRequestException
     */
    public function handle(string $channelId): DiscordChannel
    {
        return DiscordChannel::withTrashed()->find($channelId)
            ?? UpsertDiscordChannel::run(FetchChannel::run($channelId));
    }
}
