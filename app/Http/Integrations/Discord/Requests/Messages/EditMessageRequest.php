<?php

namespace App\Http\Integrations\Discord\Requests\Messages;

use App\Http\Integrations\Discord\Data\Messages\MessageData;
use App\Http\Integrations\Discord\Data\Messages\MessagePayloadData;
use Illuminate\Support\Arr;
use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\Traits\Body\HasJsonBody;

/**
 * Edits a message, sending only the payload keys Discord accepts on edit.
 */
final class EditMessageRequest extends Request implements HasBody
{
    use HasJsonBody;

    /**
     * The MessagePayloadData keys Discord's Edit Message endpoint accepts.
     *
     * @var list<string>
     */
    private const array EDITABLE_KEYS = ['content', 'embeds', 'flags', 'attachments'];

    protected Method $method = Method::PATCH;

    public function __construct(
        protected readonly string $channelId,
        protected readonly string $messageId,
        protected readonly MessagePayloadData $payload,
    ) {}

    public function resolveEndpoint(): string
    {
        return "/channels/{$this->channelId}/messages/{$this->messageId}";
    }

    /**
     * The payload's editable keys as a snake_case array, without its Optional properties.
     *
     * @return array<string, mixed>
     */
    protected function defaultBody(): array
    {
        return Arr::only($this->payload->toArray(), self::EDITABLE_KEYS);
    }

    public function createDtoFromResponse(Response $response): MessageData
    {
        return MessageData::from($response->json());
    }
}
