<?php

namespace App\Http\Integrations\WarcraftLogs;

use App\Http\Integrations\WarcraftLogs\Data\RateLimit\RateLimitData;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\Repository;

/**
 * Remembers when the Warcraft Logs points budget resets.
 *
 * WCL meters points per API key, so one entry covers every namespace host. The entry's
 * TTL is the window's remaining time, so it disappears when the points reset and the
 * next window looks the reset time up again.
 */
final class RateLimitResetCache
{
    private const string KEY = 'warcraftlogs:rate-limit-reset';

    public function __construct(
        private readonly Repository $cache,
    ) {}

    public function put(RateLimitData $rateLimit): void
    {
        $this->cache->put(
            self::KEY,
            now()->addSeconds($rateLimit->pointsResetIn)->getTimestamp(),
            $rateLimit->pointsResetIn,
        );
    }

    public function resetsAt(): ?CarbonImmutable
    {
        $timestamp = $this->cache->get(self::KEY);

        if (! is_int($timestamp)) {
            return null;
        }

        return CarbonImmutable::createFromTimestamp($timestamp);
    }

    /**
     * Seconds until the points reset, never less than 1, so a 429 in the window's
     * final second still sets a cooldown.
     */
    public function secondsUntilReset(): ?int
    {
        $resetsAt = $this->resetsAt();

        if ($resetsAt === null) {
            return null;
        }

        return max(1, (int) ceil(now()->diffInSeconds($resetsAt)));
    }
}
