<?php

namespace App\Http\Integrations\Concerns;

use App\Http\Integrations\ServedFromCache;
use Saloon\Http\PendingRequest;
use Saloon\RateLimitPlugin\Limit;
use Saloon\RateLimitPlugin\Traits\HasRateLimits;

/**
 * Saloon's rate limiting, except that requests served from the response cache are never
 * blocked or delayed by an exceeded limit. Cached responses still count towards each limit.
 */
trait HasCacheAwareRateLimits
{
    use HasRateLimits {
        handleExceededLimit as protected handleLiveExceededLimit;
    }

    abstract protected function servedFromCache(): ServedFromCache;

    protected function handleExceededLimit(Limit $limit, PendingRequest $pendingRequest): void
    {
        if ($this->servedFromCache()->check($pendingRequest)) {
            return;
        }

        $this->handleLiveExceededLimit($limit, $pendingRequest);
    }
}
