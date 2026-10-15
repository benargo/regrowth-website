<?php

namespace App\Http\Integrations\Discord\Exceptions\Concerns;

/**
 * Implements DiscordRequestException for Saloon RequestException subclasses that
 * promote `method`, `endpoint`, `discordCode` and `body` properties.
 */
trait ExposesDiscordRequest
{
    public function getMethod(): string
    {
        return $this->method;
    }

    public function getEndpoint(): string
    {
        return $this->endpoint;
    }

    public function getDiscordStatus(): int
    {
        return $this->getResponse()->status();
    }

    public function getDiscordCode(): ?int
    {
        return $this->discordCode;
    }

    /** @return array<string, mixed>|null */
    public function getDiscordBody(): ?array
    {
        return $this->body;
    }
}
