<?php

namespace Tests\Feature\Listeners;

use App\Contracts\Events\FlushesRaidingCache;
use App\Listeners\FlushRaidingCache;
use App\Models\Boss;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('raiding')]
class FlushRaidingCacheTest extends TestCase
{
    use RefreshDatabase;

    #[Group('contract')]
    #[Test]
    public function it_runs_synchronously(): void
    {
        $this->assertNotInstanceOf(ShouldQueue::class, new FlushRaidingCache);
    }

    #[Group('contract')]
    #[Test]
    public function it_listens_for_flushes_raiding_cache_events(): void
    {
        Event::fake();

        Event::assertListening(FlushesRaidingCache::class, FlushRaidingCache::class);
    }

    #[Group('happy-path')]
    #[Test]
    public function it_flushes_the_raiding_and_events_tags(): void
    {
        $this->primeCache();

        (new FlushRaidingCache)->handle(new class implements FlushesRaidingCache {});

        $this->assertFalse($this->cacheIsPrimed());
    }

    #[Test]
    public function it_leaves_other_cache_tags_alone(): void
    {
        Cache::tags(['reports'])->put('probe', true, 60);

        (new FlushRaidingCache)->handle(new class implements FlushesRaidingCache {});

        $this->assertTrue(Cache::tags(['reports'])->has('probe'));
    }

    #[Test]
    public function it_flushes_only_after_the_transaction_commits(): void
    {
        $boss = Boss::factory()->create();
        $this->primeCache();

        DB::transaction(function () use ($boss): void {
            $boss->update(['name' => 'Renamed']);

            $this->assertTrue($this->cacheIsPrimed());
        });

        $this->assertFalse($this->cacheIsPrimed());
    }

    // ==================== helpers ====================

    private function primeCache(): void
    {
        Cache::tags(['raiding', 'events'])->put('probe', true, 60);
    }

    private function cacheIsPrimed(): bool
    {
        return Cache::tags(['raiding', 'events'])->has('probe');
    }
}
