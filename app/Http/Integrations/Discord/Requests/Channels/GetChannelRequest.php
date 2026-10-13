<?php

namespace App\Http\Integrations\Discord\Requests\Channels;

use App\Http\Integrations\Discord\Data\Channels\ChannelData;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;

/**
 * Fetches a single channel by ID.
 */
final class GetChannelRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(
        protected readonly string $channelId,
    ) {}

    public function resolveEndpoint(): string
    {
        return "/channels/{$this->channelId}";
    }

    public function createDtoFromResponse(Response $response): ChannelData
    {
        return ChannelData::from($response->json());
    }
}
