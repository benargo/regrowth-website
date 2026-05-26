<?php

namespace App\Services\Discord\Exceptions;

class DiscordRequestException extends DiscordException
{
    public function __construct(
        public readonly string $method,
        public readonly string $endpoint,
        public readonly int $status,
        public readonly ?int $discordCode = null,
        public readonly ?array $body = null,
    ) {
        parent::__construct("Discord API request failed: {$this->method} {$this->endpoint} (status {$this->status})");
    }
}
