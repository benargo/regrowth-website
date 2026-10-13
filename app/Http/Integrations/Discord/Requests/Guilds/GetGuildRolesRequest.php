<?php

namespace App\Http\Integrations\Discord\Requests\Guilds;

use App\Http\Integrations\Discord\Data\Guilds\RoleData;
use Illuminate\Support\Collection;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;

/**
 * Lists a guild's roles.
 */
final class GetGuildRolesRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(
        protected readonly string $guildId,
    ) {}

    public function resolveEndpoint(): string
    {
        return "/guilds/{$this->guildId}/roles";
    }

    /**
     * @return Collection<int, RoleData>
     */
    public function createDtoFromResponse(Response $response): Collection
    {
        return RoleData::collect($response->json(), Collection::class);
    }
}
