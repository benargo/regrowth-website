<?php

namespace App\Http\Integrations\Discord\Requests\Guilds;

use App\Http\Integrations\Discord\Data\Guilds\EmojiData;
use Illuminate\Support\Collection;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;

/**
 * Lists a guild's emojis.
 */
final class GetGuildEmojisRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(
        protected readonly string $guildId,
    ) {}

    public function resolveEndpoint(): string
    {
        return "/guilds/{$this->guildId}/emojis";
    }

    /**
     * @return Collection<int, EmojiData>
     */
    public function createDtoFromResponse(Response $response): Collection
    {
        return EmojiData::collect($response->json(), Collection::class);
    }
}
