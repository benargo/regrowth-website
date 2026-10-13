<?php

namespace App\Http\Integrations\Discord\Exceptions;

use App\Http\Integrations\Discord\Exceptions\Concerns\ExposesDiscordRequest;
use Saloon\Exceptions\Request\Statuses\NotFoundException as Base;
use Saloon\Http\Response;
use Throwable;

/**
 * A 404 whose Discord JSON error code identifies a specific missing resource.
 */
abstract class NotFoundException extends Base implements DiscordRequestException
{
    use ExposesDiscordRequest;

    /**
     * A prefix for the exception message, set by child classes.
     */
    protected string $prefix = 'Resource not found:';

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
            "{$this->prefix} {$this->method} {$this->endpoint} (status {$response->status()})",
            $response->status(),
            $previous,
        );
    }
}
