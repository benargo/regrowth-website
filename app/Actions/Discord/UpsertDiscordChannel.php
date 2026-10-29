<?php

namespace App\Actions\Discord;

use App\Http\Integrations\Discord\Data\Channels\ChannelData;
use App\Models\DiscordChannel;
use Lorisleiva\Actions\Concerns\AsAction;
use Spatie\LaravelData\Optional;

/**
 * Creates or updates the stored Discord channel from Discord's channel data, restoring it if trashed.
 */
class UpsertDiscordChannel
{
    use AsAction;

    public function handle(ChannelData $channel): DiscordChannel
    {
        $discordChannel = DiscordChannel::withTrashed()->updateOrCreate(['id' => $channel->id], [
            'name' => $channel->name instanceof Optional ? null : $channel->name,
            'type' => $channel->type instanceof Optional ? null : $channel->type,
            'position' => $channel->position instanceof Optional ? 0 : $channel->position,
            'parent_id' => $channel->parentId instanceof Optional ? null : $channel->parentId,
            'topic' => $channel->topic instanceof Optional ? null : $channel->topic,
            'nsfw' => $channel->nsfw instanceof Optional ? false : $channel->nsfw,
        ]);

        if ($discordChannel->trashed()) {
            $discordChannel->restore();
        }

        return $discordChannel;
    }
}
