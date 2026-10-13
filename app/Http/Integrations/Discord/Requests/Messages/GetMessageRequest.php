<?php

namespace App\Http\Integrations\Discord\Requests\Messages;

use App\Http\Integrations\Discord\Data\Messages\MessageData;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;

/**
 * Fetches a single message from a channel.
 */
final class GetMessageRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(
        protected readonly string $channelId,
        protected readonly string $messageId,
    ) {}

    public function resolveEndpoint(): string
    {
        return "/channels/{$this->channelId}/messages/{$this->messageId}";
    }

    public function createDtoFromResponse(Response $response): MessageData
    {
        return MessageData::from($response->json());
    }
}
