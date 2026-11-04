<?php

namespace App\Http\Integrations\Discord\Requests\Guilds;

use App\Http\Integrations\Discord\Data\Guilds\GuildMemberData;
use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\Traits\Body\HasJsonBody;
use SensitiveParameter;

/**
 * Adds a user to a guild with their `guilds.join` access token. A 201 returns
 * the new member and a 204 means they were already a member.
 */
final class AddGuildMemberRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::PUT;

    public function __construct(
        protected readonly string $guildId,
        protected readonly string $userId,
        #[SensitiveParameter] protected readonly string $accessToken,
    ) {}

    public function resolveEndpoint(): string
    {
        return "/guilds/{$this->guildId}/members/{$this->userId}";
    }

    /**
     * @return array{access_token: string}
     */
    protected function defaultBody(): array
    {
        return ['access_token' => $this->accessToken];
    }

    public function createDtoFromResponse(Response $response): ?GuildMemberData
    {
        if ($response->status() === 204) {
            return null;
        }

        return GuildMemberData::from($response->json());
    }
}
