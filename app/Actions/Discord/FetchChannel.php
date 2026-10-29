<?php

namespace App\Actions\Discord;

use App\Http\Integrations\Discord\Data\Channels\ChannelData;
use App\Http\Integrations\Discord\DiscordConnector;
use App\Http\Integrations\Discord\Exceptions\ApiException;
use App\Http\Integrations\Discord\Requests\Channels\GetChannelRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Fetches a Discord channel by its ID, bypassing the response cache when fresh.
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
    public function handle(string $channelId, bool $fresh = false): ChannelData
    {
        $request = new GetChannelRequest($channelId);

        if ($fresh) {
            $request->invalidateCache();
        }

        return $this->discord->send($request)->dto();
    }
}
