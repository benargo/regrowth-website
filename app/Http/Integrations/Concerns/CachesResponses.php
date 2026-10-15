<?php

namespace App\Http\Integrations\Concerns;

use Illuminate\Support\Facades\Cache;
use Saloon\CachePlugin\Contracts\Driver;
use Saloon\CachePlugin\Drivers\LaravelCacheDriver;
use Saloon\CachePlugin\Traits\HasCaching as HasSaloonCaching;

/**
 * Caches responses in the default Laravel cache store under the request's cache tags.
 */
trait CachesResponses
{
    use HasSaloonCaching;

    /**
     * @return array<int, string>
     */
    abstract protected function cacheTags(): array;

    public function resolveCacheDriver(): Driver
    {
        return new LaravelCacheDriver(
            Cache::store()->tags($this->cacheTags())
        );
    }
}
