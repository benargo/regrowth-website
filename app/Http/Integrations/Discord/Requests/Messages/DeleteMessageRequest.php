<?php

namespace App\Http\Integrations\Discord\Requests\Messages;

use Saloon\Enums\Method;
use Saloon\Http\Request;

final class DeleteMessageRequest extends Request
{
    protected Method $method = Method::DELETE;

    public function __construct(
        protected readonly string $channelId,
        protected readonly string $messageId,
    ) {}

    public function resolveEndpoint(): string
    {
        return "/channels/{$this->channelId}/messages/{$this->messageId}";
    }
}
