<?php

namespace Tests\Feature\Listeners;

use App\Contracts\Actions\GameVersion\CachesSelectableGameVersions;
use App\Contracts\Events\FlushesGameVersionsCache;
use App\Events\GameVersionChanged;
use App\Events\WarcraftLogsGuildDeleted;
use App\Listeners\FlushGameVersionsCache;
use App\Models\GameVersion;
use App\Models\WarcraftLogs\Guild;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('platform')]
class FlushGameVersionsCacheTest extends TestCase
{
    // ==================== contract ====================

    #[Group('contract')]
    #[Test]
    public function it_runs_synchronously(): void
    {
        $this->assertNotInstanceOf(ShouldQueue::class, $this->app->make(FlushGameVersionsCache::class));
    }

    #[Group('contract')]
    #[Test]
    public function it_listens_for_flushes_game_versions_cache_events(): void
    {
        Event::fake();

        Event::assertListening(FlushesGameVersionsCache::class, FlushGameVersionsCache::class);
    }

    // ==================== handle ====================

    #[Group('happy-path')]
    #[Test]
    public function it_flushes_the_cache_for_a_game_version_change(): void
    {
        $this->primeCache();

        $this->app->make(FlushGameVersionsCache::class)->handle(new GameVersionChanged(new GameVersion));

        $this->assertFalse($this->cacheIsPrimed());
    }

    #[Test]
    public function it_flushes_the_cache_for_a_deleted_warcraft_logs_guild(): void
    {
        $this->primeCache();

        $this->app->make(FlushGameVersionsCache::class)->handle(new WarcraftLogsGuildDeleted(new Guild));

        $this->assertFalse($this->cacheIsPrimed());
    }

    #[Test]
    public function it_forgets_the_list_memoised_for_this_request(): void
    {
        request()->attributes->set(CachesSelectableGameVersions::REQUEST_KEY, new Collection);

        $this->app->make(FlushGameVersionsCache::class)->handle(new GameVersionChanged(new GameVersion));

        $this->assertFalse(request()->attributes->has(CachesSelectableGameVersions::REQUEST_KEY));
    }

    #[Test]
    public function it_leaves_other_cache_tags_alone(): void
    {
        Cache::tags(['raiding'])->put('probe', true, 60);

        $this->app->make(FlushGameVersionsCache::class)->handle(new GameVersionChanged(new GameVersion));

        $this->assertTrue(Cache::tags(['raiding'])->has('probe'));
    }

    // ==================== helpers ====================

    private function primeCache(): void
    {
        Cache::tags([CachesSelectableGameVersions::CACHE_TAG])->put('probe', true, 60);
    }

    private function cacheIsPrimed(): bool
    {
        return Cache::tags([CachesSelectableGameVersions::CACHE_TAG])->has('probe');
    }
}
