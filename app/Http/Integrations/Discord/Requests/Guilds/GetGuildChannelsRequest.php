<?php

namespace App\Http\Integrations\Discord\Requests\Guilds;

use App\Http\Integrations\Discord\Data\Channels\ChannelData;
use Illuminate\Support\Collection;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;

/**
 * Lists a guild's channels (threads excluded).
 */
final class GetGuildChannelsRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(
        protected readonly string $guildId,
    ) {}

    public function resolveEndpoint(): string
    {
        return "/guilds/{$this->guildId}/channels";
    }

    /**
     * @return Collection<int, ChannelData>
     */
    public function createDtoFromResponse(Response $response): Collection
    {
        return ChannelData::collect($response->json(), Collection::class);
    }
}
