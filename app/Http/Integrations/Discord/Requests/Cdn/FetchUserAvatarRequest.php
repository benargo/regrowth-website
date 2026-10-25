<?php

namespace App\Http\Integrations\Discord\Requests\Cdn;

use Saloon\Enums\Method;
use Saloon\Http\Request;

/**
 * Fetches a user's avatar image from the Discord CDN.
 */
final class FetchUserAvatarRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(
        protected readonly string $userId,
        protected readonly string $hash,
    ) {}

    public function resolveEndpoint(): string
    {
        return "/avatars/{$this->userId}/{$this->hash}.webp";
    }
}
