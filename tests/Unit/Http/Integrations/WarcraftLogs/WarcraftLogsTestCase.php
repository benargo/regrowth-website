<?php

namespace Tests\Unit\Http\Integrations\WarcraftLogs;

use App\Http\Integrations\WarcraftLogs\RateLimitResetCache;
use Tests\Concerns\FakesWarcraftLogs;
use Tests\TestCase;

abstract class WarcraftLogsTestCase extends TestCase
{
    use FakesWarcraftLogs;

    protected function resetCache(): RateLimitResetCache
    {
        return $this->app->make(RateLimitResetCache::class);
    }
}
