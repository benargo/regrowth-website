<?php

namespace App\Http\Integrations\Discord\Requests\Guilds;

use App\Http\Integrations\Discord\Contracts\PaginatesBySnowflake;
use App\Http\Integrations\Discord\Data\Guilds\GuildMemberData;
use Illuminate\Support\Collection;
use Illuminate\Support\Number;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;

/**
 * Lists one page of guild members whose user IDs are greater than `after`, ordered by user ID.
 */
final class ListGuildMembersRequest extends Request implements PaginatesBySnowflake
{
    protected Method $method = Method::GET;

    public function __construct(
        protected readonly string $guildId,
        protected readonly int $limit = 1000,
        protected readonly string $after = '0',
    ) {}

    public function resolveEndpoint(): string
    {
        return "/guilds/{$this->guildId}/members";
    }

    /**
     * The page size, clamped to Discord's 1–1000 range, and the user ID to start after.
     *
     * @return array{limit: int, after: string}
     */
    protected function defaultQuery(): array
    {
        return [
            'limit' => Number::clamp($this->limit, 1, 1000),
            'after' => $this->after,
        ];
    }

    public function cursorKey(): string
    {
        return 'user.id';
    }

    public function cursorDirection(): string
    {
        return 'after';
    }

    /**
     * @return Collection<int, GuildMemberData>
     */
    public function createDtoFromResponse(Response $response): Collection
    {
        return GuildMemberData::collect($response->json(), Collection::class);
    }
}
