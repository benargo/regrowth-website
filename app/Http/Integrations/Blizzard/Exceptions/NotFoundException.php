<?php

namespace App\Http\Integrations\Blizzard\Exceptions;

use Saloon\Exceptions\Request\Statuses\NotFoundException as Base;
use Saloon\Http\Response;
use Throwable;

abstract class NotFoundException extends Base implements BlizzardRequestException
{
    /**
     * A prefix for the exception message, to be set by child classes.
     */
    protected string $prefix = 'Resource not found:';

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
            "{$this->prefix} {$this->method} {$this->endpoint} (status {$this->blizzardStatus})",
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
