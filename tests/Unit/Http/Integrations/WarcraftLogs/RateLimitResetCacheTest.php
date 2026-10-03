<?php

namespace Tests\Unit\Http\Integrations\WarcraftLogs;

use App\Http\Integrations\WarcraftLogs\Data\RateLimit\RateLimitData;
use App\Http\Integrations\WarcraftLogs\RateLimitResetCache;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('warcraftlogs-integration')]
class RateLimitResetCacheTest extends TestCase
{
    #[Test]
    public function it_returns_null_when_nothing_is_cached(): void
    {
        $this->assertNull($this->resetCache()->resetsAt());
        $this->assertNull($this->resetCache()->secondsUntilReset());
    }

    #[Test]
    #[Group('happy-path')]
    public function it_round_trips_the_reset_moment(): void
    {
        $this->freezeTime();

        $this->resetCache()->put($this->rateLimit(pointsResetIn: 1200));

        $this->assertSame(now()->addSeconds(1200)->getTimestamp(), $this->resetCache()->resetsAt()?->getTimestamp());
        $this->assertSame(1200, $this->resetCache()->secondsUntilReset());
    }

    #[Test]
    public function it_counts_down_to_one_second_before_the_reset(): void
    {
        $this->freezeTime();
        $this->resetCache()->put($this->rateLimit(pointsResetIn: 1200));

        $this->travel(1199)->seconds();

        $this->assertSame(1, $this->resetCache()->secondsUntilReset());
    }

    #[Test]
    public function it_expires_when_the_points_reset(): void
    {
        $this->freezeTime();
        $this->resetCache()->put($this->rateLimit(pointsResetIn: 1200));

        $this->travel(1200)->seconds();

        $this->assertNull($this->resetCache()->resetsAt());
    }

    #[Test]
    #[Group('edge-case')]
    public function it_caches_nothing_when_points_reset_now(): void
    {
        $this->resetCache()->put($this->rateLimit(pointsResetIn: 0));

        $this->assertNull($this->resetCache()->resetsAt());
        $this->assertNull($this->resetCache()->secondsUntilReset());
    }

    #[Test]
    public function flushing_the_rate_limit_tag_clears_the_reset_moment(): void
    {
        $this->resetCache()->put($this->rateLimit(pointsResetIn: 1200));

        Cache::tags(['warcraftlogs-rate-limit'])->flush();

        $this->assertNull($this->resetCache()->resetsAt());
    }

    #[Test]
    #[Group('contract')]
    public function it_is_bound_as_a_singleton(): void
    {
        $this->assertSame($this->resetCache(), $this->resetCache());
    }

    private function resetCache(): RateLimitResetCache
    {
        return $this->app->make(RateLimitResetCache::class);
    }

    private function rateLimit(int $pointsResetIn): RateLimitData
    {
        return new RateLimitData(limitPerHour: 3600, pointsSpentThisHour: 1800.0, pointsResetIn: $pointsResetIn);
    }
}
