<?php

namespace App\Http\Integrations\Discord\Exceptions;

use RuntimeException;
use Saloon\Http\Response;
use Throwable;

/**
 * Discord is rate limiting this route, or every route when $global. $response is
 * null when GuardRateLimits refused the request before sending it.
 */
final class RateLimitedException extends RuntimeException
{
    /**
     * @param  float  $retryAfter  Seconds until the limit resets.
     * @param  string  $scope  `user`, `global` or `shared`.
     */
    public function __construct(
        public readonly string $method,
        public readonly string $endpoint,
        public readonly float $retryAfter,
        public readonly string $scope = 'user',
        public readonly bool $global = false,
        public readonly ?Response $response = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            "Discord API rate limit exceeded for {$this->method} {$this->endpoint}. Retry after {$this->retryAfter}s (scope: {$this->scope}).",
            429,
            $previous,
        );
    }

    /**
     * Seconds until the limit resets, rounded up to a whole second.
     */
    public function releaseAfter(): int
    {
        return max(1, (int) ceil($this->retryAfter));
    }
}
