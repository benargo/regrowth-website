<?php

namespace App\Http\Integrations\Blizzard\Concerns;

use Illuminate\Support\Facades\Cache;
use Saloon\CachePlugin\Contracts\Driver;
use Saloon\CachePlugin\Drivers\LaravelCacheDriver;
use Saloon\CachePlugin\Traits\HasCaching as HasSaloonCaching;

trait HasCaching
{
    use HasSaloonCaching;

    public function resolveCacheDriver(): Driver
    {
        return new LaravelCacheDriver(
            Cache::store()->tags(['blizzard', 'blizzard-api-response'])
        );
    }
}
