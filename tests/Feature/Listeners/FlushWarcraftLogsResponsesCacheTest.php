<?php

namespace Tests\Feature\Listeners;

use App\Events\WarcraftLogsGuildSaved;
use App\Http\Integrations\WarcraftLogs\WarcraftLogsNamespace;
use App\Listeners\FlushWarcraftLogsResponsesCache;
use App\Models\WarcraftLogs\Guild;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('warcraftlogs-integration')]
class FlushWarcraftLogsResponsesCacheTest extends TestCase
{
    use RefreshDatabase;

    // ==================== contract ====================

    #[Group('contract')]
    #[Test]
    public function it_runs_synchronously(): void
    {
        $this->assertNotInstanceOf(ShouldQueue::class, new FlushWarcraftLogsResponsesCache);
    }

    #[Group('contract')]
    #[Test]
    public function it_listens_for_warcraft_logs_guild_saved_events(): void
    {
        Event::fake();

        Event::assertListening(WarcraftLogsGuildSaved::class, FlushWarcraftLogsResponsesCache::class);
    }

    // ==================== handle ====================

    #[Group('happy-path')]
    #[Test]
    public function creating_a_guild_flushes_the_cached_warcraft_logs_api_responses(): void
    {
        $this->primeCache();

        Guild::factory()->create();

        $this->assertFalse($this->cacheIsPrimed());
    }

    #[Test]
    public function changing_the_namespace_flushes_the_cached_warcraft_logs_api_responses(): void
    {
        $guild = Guild::factory()->create(['namespace' => WarcraftLogsNamespace::Anniversary]);
        $this->primeCache();

        $guild->update(['namespace' => WarcraftLogsNamespace::Classic]);

        $this->assertFalse($this->cacheIsPrimed());
    }

    #[Test]
    public function saving_without_a_namespace_change_keeps_the_cached_responses(): void
    {
        $guild = Guild::factory()->create(['namespace' => WarcraftLogsNamespace::Anniversary]);
        $this->primeCache();

        $guild->touch();

        $this->assertTrue($this->cacheIsPrimed());
    }

    #[Test]
    public function changing_the_namespace_leaves_unrelated_cache_tags_alone(): void
    {
        $guild = Guild::factory()->create(['namespace' => WarcraftLogsNamespace::Anniversary]);
        Cache::tags(['attendance'])->put('unrelated_key', 'unrelated', now()->addMinutes(5));

        $guild->update(['namespace' => WarcraftLogsNamespace::Classic]);

        $this->assertTrue(Cache::tags(['attendance'])->has('unrelated_key'));
    }

    private function primeCache(): void
    {
        Cache::tags(['warcraftlogs', 'warcraftlogs-api-response'])->put('response_key', 'response', now()->addMinutes(5));
    }

    private function cacheIsPrimed(): bool
    {
        return Cache::tags(['warcraftlogs', 'warcraftlogs-api-response'])->has('response_key');
    }
}
