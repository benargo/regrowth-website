<?php

namespace App\Http\Integrations\Discord\Exceptions;

use App\Http\Integrations\Discord\Exceptions\Concerns\ExposesDiscordRequest;
use Saloon\Exceptions\Request\ClientException;
use Saloon\Http\Response;
use Throwable;

/**
 * Any failed Discord response, including 5xx, that has no more specific exception.
 */
class ApiException extends ClientException implements DiscordRequestException
{
    use ExposesDiscordRequest;

    /**
     * @param  array<string, mixed>|null  $body
     */
    public function __construct(
        Response $response,
        public readonly string $method,
        public readonly string $endpoint,
        public readonly ?int $discordCode = null,
        public readonly ?array $body = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            $response,
            "Discord API request failed: {$this->method} {$this->endpoint} (status {$response->status()})",
            $response->status(),
            $previous,
        );
    }
}
