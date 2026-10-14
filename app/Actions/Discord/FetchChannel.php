<?php

namespace App\Actions\Discord;

use App\Http\Integrations\Discord\Data\Channels\ChannelData;
use App\Http\Integrations\Discord\DiscordConnector;
use App\Http\Integrations\Discord\Exceptions\ApiException;
use App\Http\Integrations\Discord\Requests\Channels\GetChannelRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Fetches a Discord channel by its ID.
 */
class FetchChannel
{
    use AsAction;

    public function __construct(
        protected DiscordConnector $discord,
    ) {}

    /**
     * @throws ApiException
     */
    public function handle(string $channelId): ChannelData
    {
        return $this->discord->send(new GetChannelRequest($channelId))->dto();
    }
}
