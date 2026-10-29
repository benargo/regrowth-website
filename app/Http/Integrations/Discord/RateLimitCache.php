<?php

namespace App\Http\Integrations\Discord;

use Illuminate\Contracts\Cache\Repository;

/**
 * Stores Discord's per-route and global rate limits, each expiring when its limit resets.
 */
final class RateLimitCache
{
    private const string GLOBAL_KEY = 'discord:rate-limit:global';

    private const string ROUTE_KEY_PREFIX = 'discord:rate-limit:route:';

    public function __construct(
        private readonly Repository $cache,
    ) {}

    public function blockRoute(string $route, float $seconds, string $scope): void
    {
        $this->cache->put(
            self::ROUTE_KEY_PREFIX.$route,
            ['until' => $this->now() + $seconds, 'scope' => $scope],
            max(1, (int) ceil($seconds)),
        );
    }

    public function blockGlobally(float $seconds): void
    {
        $this->cache->put(self::GLOBAL_KEY, ['until' => $this->now() + $seconds], max(1, (int) ceil($seconds)));
    }

    /**
     * @return array{retryAfter: float, scope: string}|null
     */
    public function routeBlock(string $route): ?array
    {
        $entry = $this->cache->get(self::ROUTE_KEY_PREFIX.$route);
        $retryAfter = $this->remaining($entry);

        if ($retryAfter === null) {
            return null;
        }

        return ['retryAfter' => $retryAfter, 'scope' => (string) ($entry['scope'] ?? 'user')];
    }

    public function globalBlock(): ?float
    {
        return $this->remaining($this->cache->get(self::GLOBAL_KEY));
    }

    private function remaining(mixed $entry): ?float
    {
        if (! is_array($entry) || ! is_numeric($entry['until'] ?? null)) {
            return null;
        }

        $remaining = (float) $entry['until'] - $this->now();

        return $remaining > 0 ? $remaining : null;
    }

    /**
     * The current Unix timestamp in seconds, to the millisecond.
     */
    private function now(): float
    {
        return now()->getTimestampMs() / 1000;
    }
}
