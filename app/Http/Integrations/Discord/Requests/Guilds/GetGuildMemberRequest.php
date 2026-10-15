<?php

namespace App\Http\Integrations\Discord\Requests\Guilds;

use App\Http\Integrations\Discord\Data\Guilds\GuildMemberData;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;

/**
 * A 404 with code 10007 (Unknown Member) is thrown as UserNotInGuildException by the connector.
 */
final class GetGuildMemberRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(
        protected readonly string $guildId,
        protected readonly string $userId,
    ) {}

    public function resolveEndpoint(): string
    {
        return "/guilds/{$this->guildId}/members/{$this->userId}";
    }

    public function createDtoFromResponse(Response $response): GuildMemberData
    {
        return GuildMemberData::from($response->json());
    }
}
