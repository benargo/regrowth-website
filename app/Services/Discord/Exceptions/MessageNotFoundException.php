<?php

namespace App\Services\Discord\Exceptions;

class MessageNotFoundException extends DiscordRequestException
{
    public function __construct(string $method, string $endpoint, int $status, ?int $discordCode = null, ?array $body = null)
    {
        parent::__construct($method, $endpoint, $status, $discordCode, $body);

        $this->message = "Message not found: {$method} {$endpoint}";
    }
}
