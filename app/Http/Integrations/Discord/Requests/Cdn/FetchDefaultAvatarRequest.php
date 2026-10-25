<?php

namespace App\Http\Integrations\Discord\Requests\Cdn;

use Saloon\Enums\Method;
use Saloon\Http\Request;

/**
 * Fetches a default avatar image from the Discord CDN.
 */
final class FetchDefaultAvatarRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(
        protected readonly int $index,
    ) {}

    public function resolveEndpoint(): string
    {
        return "/embed/avatars/{$this->index}.png";
    }
}
