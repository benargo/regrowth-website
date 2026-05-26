<?php

namespace App\Services\Discord\Exceptions;

class RateLimitedException extends DiscordException
{
    public function __construct(
        public readonly string $endpoint,
        public readonly float $retryAfter,
        public readonly string $scope = 'user',
    ) {
        parent::__construct(
            "Discord API rate limit exceeded for {$this->endpoint}. Retry after {$this->retryAfter}s (scope: {$this->scope})."
        );
    }
}
