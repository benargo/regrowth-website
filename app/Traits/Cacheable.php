<?php

namespace App\Traits;

use App\Exceptions\CacheException;
use Illuminate\Support\Facades\Cache;

trait Cacheable
{
    private int $cacheTtl = 3600; // Default cache TTL in seconds (1 hour)

    private ?bool $shouldUseFresh = null;

    private bool $shouldIgnoreCache = false;

    /**
     * Get a value from the cache, or compute and store it if not present.
     *
     * @param  string  $key  The cache key.
     * @param  int  $ttl  Time to live in seconds.
     * @param  callable  $callback  Function to compute the value if not cached.
     * @return mixed The cached or computed value.
     *
     * @throws CacheException If the cache key is not found when explicitly using cached data.
     */
    private function cacheable(string $key, ?int $ttl, callable $callback)
    {
        if ($this->shouldIgnoreCache) {
            $this->shouldIgnoreCache = false; // Reset after use

            return $callback();
        }

        if ($this->shouldUseFresh === true) {
            $this->shouldUseFresh = null; // Reset after use
            Cache::forget($key);
        }

        if ($this->shouldUseFresh === false) {
            $this->shouldUseFresh = null; // Reset after use

            if (! Cache::has($key)) {
                return throw new CacheException("Cache key '{$key}' not found after explicit cache use.");
            }

            return Cache::get($key);
        }

        $ttl = $ttl ?? $this->cacheTtl;

        return Cache::remember($key, $ttl, $callback);
    }

    /**
     * Set whether to explicitly use fresh data, or explicity use cached data.
     *
     * @param  bool  $shouldUseFresh  Whether to use fresh data.
     */
    public function fresh(?bool $shouldUseFresh = true): static
    {
        $this->shouldUseFresh = $shouldUseFresh;

        return $this;
    }

    /**
     * Set whether to ignore the cache and always compute fresh data.
     */
    public function ignoreCache(): static
    {
        $this->shouldIgnoreCache = true;

        return $this;
    }
}
