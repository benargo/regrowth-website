<?php

namespace App\Http\Integrations\WarcraftLogs\Concerns;

use Illuminate\Support\Facades\Cache;
use Saloon\CachePlugin\Contracts\Driver;
use Saloon\CachePlugin\Drivers\LaravelCacheDriver;
use Saloon\CachePlugin\Traits\HasCaching as HasSaloonCaching;
use Saloon\Enums\Method;
use Saloon\Http\PendingRequest;

trait HasCaching
{
    use HasSaloonCaching;

    public function resolveCacheDriver(): Driver
    {
        return new LaravelCacheDriver(
            Cache::store()->tags(['warcraftlogs', 'warcraftlogs-api-response'])
        );
    }

    /**
     * GraphQL reads are POSTs.
     *
     * @return array<int, Method>
     */
    protected function getCacheableMethods(): array
    {
        return [Method::GET, Method::OPTIONS, Method::POST];
    }

    /**
     * The URL tells namespace hosts apart, and the body tells queries and variables
     * (including the page) apart. Headers are left out, so a rotated bearer token does
     * not bust the cache. JSON_THROW_ON_ERROR stops a body that cannot be encoded from
     * putting every request on one `false` key.
     */
    protected function cacheKey(PendingRequest $pendingRequest): ?string
    {
        $body = json_encode($pendingRequest->body()?->all(), JSON_THROW_ON_ERROR);

        return "warcraftlogs:{$pendingRequest->getUrl()}:{$body}";
    }
}
