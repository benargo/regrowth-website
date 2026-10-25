<?php

namespace App\Http\Integrations\Discord\Requests\Cdn;

use Saloon\Enums\Method;
use Saloon\Http\Request;

/**
 * Fetches a guild member's avatar image from the Discord CDN.
 */
final class FetchGuildMemberAvatarRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(
        protected readonly string $guildId,
        protected readonly string $userId,
        protected readonly string $hash,
    ) {}

    public function resolveEndpoint(): string
    {
        return "/guilds/{$this->guildId}/users/{$this->userId}/avatars/{$this->hash}.webp";
    }
}
