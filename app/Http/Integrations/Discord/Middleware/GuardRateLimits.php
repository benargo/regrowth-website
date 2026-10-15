<?php

namespace App\Http\Integrations\Discord\Middleware;

use App\Http\Integrations\Discord\Exceptions\RateLimitedException;
use App\Http\Integrations\Discord\RateLimitCache;
use App\Http\Integrations\ServedFromCache;
use Saloon\Http\PendingRequest;

/**
 * Throws RateLimitedException before sending while the route, or every route, is rate limited.
 * Requests served from the response cache pass through.
 */
final class GuardRateLimits
{
    public function __construct(
        private readonly RateLimitCache $rateLimits,
        private readonly ServedFromCache $servedFromCache,
    ) {}

    public function __invoke(PendingRequest $pendingRequest): void
    {
        if ($this->servedFromCache->check($pendingRequest)) {
            return;
        }

        $method = $pendingRequest->getMethod()->value;
        $endpoint = ltrim($pendingRequest->getRequest()->resolveEndpoint(), '/');

        $globalRetryAfter = $this->rateLimits->globalBlock();

        if ($globalRetryAfter !== null) {
            throw new RateLimitedException($method, $endpoint, $globalRetryAfter, scope: 'global', global: true);
        }

        $routeBlock = $this->rateLimits->routeBlock(RecordRateLimits::route($method, $endpoint));

        if ($routeBlock !== null) {
            throw new RateLimitedException($method, $endpoint, $routeBlock['retryAfter'], scope: $routeBlock['scope']);
        }
    }
}
