<?php

namespace App\Http\Integrations\Blizzard\Exceptions;

use App\Services\Blizzard\Exceptions\BlizzardRequestException;
use Saloon\Exceptions\Request\Statuses\NotFoundException;
use Saloon\Http\Response;
use Throwable;

class InvalidRaceException extends NotFoundException implements BlizzardRequestException
{
    /**
     * @param  array<string, mixed>|null  $body
     */
    public function __construct(
        public readonly string $method,
        public readonly string $endpoint,
        public readonly int $blizzardStatus,
        Response $response,
        public readonly ?string $blizzardCode = null,
        public readonly ?array $body = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            $response,
            "Playable race not found: {$this->method} {$this->endpoint} (status {$this->blizzardStatus})",
            $this->blizzardStatus,
            $previous,
        );
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getEndpoint(): string
    {
        return $this->endpoint;
    }

    public function getBlizzardStatus(): int
    {
        return $this->blizzardStatus;
    }

    public function getBlizzardCode(): ?string
    {
        return $this->blizzardCode;
    }

    /** @return array<string, mixed>|null */
    public function getBlizzardBody(): ?array
    {
        return $this->body;
    }
}
