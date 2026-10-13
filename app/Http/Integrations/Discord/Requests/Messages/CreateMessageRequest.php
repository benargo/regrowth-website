<?php

namespace App\Http\Integrations\Discord\Requests\Messages;

use App\Http\Integrations\Discord\Data\Messages\MessageData;
use App\Http\Integrations\Discord\Data\Messages\MessagePayloadData;
use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\Traits\Body\HasJsonBody;

/**
 * Posts a message to a channel.
 */
final class CreateMessageRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    public function __construct(
        protected readonly string $channelId,
        protected readonly MessagePayloadData $payload,
    ) {}

    public function resolveEndpoint(): string
    {
        return "/channels/{$this->channelId}/messages";
    }

    /**
     * The payload as a snake_case array, without its Optional properties.
     *
     * @return array<string, mixed>
     */
    protected function defaultBody(): array
    {
        return $this->payload->toArray();
    }

    public function createDtoFromResponse(Response $response): MessageData
    {
        return MessageData::from($response->json());
    }
}
