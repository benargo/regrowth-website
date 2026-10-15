<?php

namespace App\Http\Integrations\Discord\Requests\Guilds;

use App\Http\Integrations\Discord\Data\Guilds\GuildMemberData;
use Illuminate\Support\Collection;
use Illuminate\Support\Number;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;

/**
 * Lists guild members whose username or nickname starts with the search query.
 */
final class SearchGuildMembersRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(
        protected readonly string $guildId,
        protected readonly string $searchQuery,
        protected readonly int $limit = 1,
    ) {}

    public function resolveEndpoint(): string
    {
        return "/guilds/{$this->guildId}/members/search";
    }

    /**
     * The search term and the limit, clamped to Discord's 1–1000 range.
     *
     * @return array{query: string, limit: int}
     */
    protected function defaultQuery(): array
    {
        return [
            'query' => $this->searchQuery,
            'limit' => Number::clamp($this->limit, 1, 1000),
        ];
    }

    /**
     * @return Collection<int, GuildMemberData>
     */
    public function createDtoFromResponse(Response $response): Collection
    {
        return GuildMemberData::collect($response->json(), Collection::class);
    }
}
