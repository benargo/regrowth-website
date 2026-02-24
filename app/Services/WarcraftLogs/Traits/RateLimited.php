<?php

namespace App\Services\WarcraftLogs\Traits;

use App\Services\WarcraftLogs\Exceptions\RateLimitedException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

trait RateLimited
{
    protected const RATE_LIMIT_CACHE_KEY = 'warcraftlogs.rate_limited';

    protected const RATE_LIMIT_COOLDOWN = 3600; // 1 hour

    protected const RATE_LIMIT_INFO_CACHE_KEY = 'warcraftlogs.rate_limit';

    /**
     * Check if the API is currently rate limited and throw if so.
     *
     * @throws RateLimitedException
     */
    protected function ensureNotRateLimited(): void
    {
        if (Cache::has(self::RATE_LIMIT_CACHE_KEY)) {
            throw new RateLimitedException;
        }
    }

    /**
     * Activate the rate limit cooldown, preventing further requests for one hour.
     */
    protected function activateRateLimitCooldown(): void
    {
        Cache::put(self::RATE_LIMIT_CACHE_KEY, true, self::RATE_LIMIT_COOLDOWN);

        Log::warning('WarcraftLogs API rate limit exceeded. Pausing requests for one hour.');
    }

    /**
     * Track rate limit information from API response headers.
     */
    protected function trackRateLimitHeaders(Response $response): void
    {
        $limitHeader = $response->header('x-ratelimit-limit');
        $remainingHeader = $response->header('x-ratelimit-remaining');

        if ($limitHeader === '' || $remainingHeader === '') {
            return;
        }

        $limit = (int) $limitHeader;
        $remaining = (int) $remainingHeader;

        Cache::put(self::RATE_LIMIT_INFO_CACHE_KEY, [
            'limit' => $limit,
            'remaining' => $remaining,
        ], self::RATE_LIMIT_COOLDOWN);

        if ($limit > 0 && $remaining <= (int) ceil($limit * 0.1)) {
            Log::warning('WarcraftLogs API rate limit tokens running low.', [
                'remaining' => $remaining,
                'limit' => $limit,
            ]);
        }
    }
}
