<?php

namespace Tests\Feature\Listeners;

use App\Contracts\Events\FlushesRaidingCache;
use App\Listeners\FlushRaidingCache;
use App\Models\Boss;
use App\Models\GameVersion;
use App\Models\Raid;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Spatie\MediaLibrary\Conversions\Conversion;
use Spatie\MediaLibrary\Conversions\Events\ConversionHasBeenCompletedEvent;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
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

    #[Group('contract')]
    #[Test]
    public function it_listens_for_conversion_completed_events(): void
    {
        Event::fake();

        Event::assertListening(ConversionHasBeenCompletedEvent::class, FlushRaidingCache::class.'@handleConversionCompleted');
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

    #[Group('laravel-media-library')]
    #[Test]
    public function it_flushes_the_cache_when_a_raid_media_conversion_completes(): void
    {
        $this->primeCache();

        (new FlushRaidingCache)->handleConversionCompleted($this->conversionCompletedFor(new Raid));

        $this->assertFalse($this->cacheIsPrimed());
    }

    #[Group('laravel-media-library')]
    #[Test]
    public function it_ignores_conversions_completed_on_other_models(): void
    {
        $this->primeCache();

        (new FlushRaidingCache)->handleConversionCompleted($this->conversionCompletedFor(new GameVersion));

        $this->assertTrue($this->cacheIsPrimed());
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

    private function conversionCompletedFor(Model $model): ConversionHasBeenCompletedEvent
    {
        return new ConversionHasBeenCompletedEvent(
            (new Media)->forceFill(['model_type' => $model->getMorphClass()]),
            new Conversion('header'),
        );
    }

    private function primeCache(): void
    {
        Cache::tags(['raiding', 'events'])->put('probe', true, 60);
    }

    private function cacheIsPrimed(): bool
    {
        return Cache::tags(['raiding', 'events'])->has('probe');
    }
}
