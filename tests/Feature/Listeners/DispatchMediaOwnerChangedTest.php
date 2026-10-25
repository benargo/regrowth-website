<?php

namespace Tests\Feature\Listeners;

use App\Contracts\Http\Middleware\SharesDeferredCacheFlushes;
use App\Events\GameVersionChanged;
use App\Events\RaidingDataChanged;
use App\Listeners\DispatchMediaOwnerChanged;
use App\Models\Boss;
use App\Models\GameVersion;
use App\Models\Item;
use App\Models\Raid;
use Closure;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Spatie\MediaLibrary\Conversions\Conversion;
use Spatie\MediaLibrary\Conversions\Events\ConversionHasBeenCompletedEvent;
use Spatie\MediaLibrary\MediaCollections\Events\CollectionHasBeenClearedEvent;
use Spatie\MediaLibrary\MediaCollections\Events\MediaHasBeenAddedEvent;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\TestCase;

#[Group('platform')]
#[Group('media')]
class DispatchMediaOwnerChangedTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{class-string<Model>, class-string, Closure}>
     */
    public static function owners(): array
    {
        return [
            'game version' => [GameVersion::class, GameVersionChanged::class, fn (GameVersionChanged $event) => $event->gameVersion],
            'raid' => [Raid::class, RaidingDataChanged::class, fn (RaidingDataChanged $event) => $event->model],
            'boss' => [Boss::class, RaidingDataChanged::class, fn (RaidingDataChanged $event) => $event->model],
        ];
    }

    #[Group('contract')]
    #[Test]
    public function it_runs_synchronously(): void
    {
        $this->assertNotInstanceOf(ShouldQueue::class, $this->app->make(DispatchMediaOwnerChanged::class));
    }

    #[Group('contract')]
    #[Test]
    public function it_listens_for_media_library_events(): void
    {
        Event::fake();

        Event::assertListening(MediaHasBeenAddedEvent::class, DispatchMediaOwnerChanged::class.'@handleMediaAdded');
        Event::assertListening(CollectionHasBeenClearedEvent::class, DispatchMediaOwnerChanged::class.'@handleCollectionCleared');
        Event::assertListening(ConversionHasBeenCompletedEvent::class, DispatchMediaOwnerChanged::class.'@handleConversionCompleted');
    }

    #[Group('happy-path')]
    #[Group('laravel-media-library')]
    #[Test]
    #[DataProvider('owners')]
    public function it_dispatches_the_owners_event_when_media_is_added(string $modelClass, string $eventClass, Closure $owner): void
    {
        $model = $modelClass::factory()->create();
        Event::fake([$eventClass]);

        $this->listener()->handleMediaAdded(new MediaHasBeenAddedEvent($this->mediaFor($model)));

        $this->assertSame(1, $this->dispatchedFor($eventClass, $owner, $model));
    }

    #[Group('laravel-media-library')]
    #[Test]
    #[DataProvider('owners')]
    public function it_dispatches_again_when_the_application_terminates(string $modelClass, string $eventClass, Closure $owner): void
    {
        $model = $modelClass::factory()->create();
        Event::fake([$eventClass]);

        $this->listener()->handleMediaAdded(new MediaHasBeenAddedEvent($this->mediaFor($model)));
        $this->app->terminate();

        $this->assertSame(2, $this->dispatchedFor($eventClass, $owner, $model));
    }

    #[Group('laravel-media-library')]
    #[Test]
    public function it_registers_one_terminating_dispatch_per_owner(): void
    {
        $boss = Boss::factory()->create();
        Event::fake([RaidingDataChanged::class]);
        $listener = $this->listener();

        $listener->handleMediaAdded(new MediaHasBeenAddedEvent($this->mediaFor($boss)));
        $listener->handleMediaAdded(new MediaHasBeenAddedEvent($this->mediaFor($boss)));
        $this->app->terminate();

        $this->assertSame(3, $this->dispatchedFor(RaidingDataChanged::class, fn (RaidingDataChanged $event) => $event->model, $boss));
    }

    #[Group('laravel-media-library')]
    #[Test]
    public function it_skips_the_terminating_dispatch_while_cache_flushes_are_deferred(): void
    {
        $boss = Boss::factory()->create();
        Event::fake([RaidingDataChanged::class]);
        request()->attributes->set(SharesDeferredCacheFlushes::DEFERRING_KEY, true);

        $this->listener()->handleMediaAdded(new MediaHasBeenAddedEvent($this->mediaFor($boss)));
        $this->app->terminate();

        $this->assertSame(1, $this->dispatchedFor(RaidingDataChanged::class, fn (RaidingDataChanged $event) => $event->model, $boss));
    }

    #[Group('laravel-media-library')]
    #[Test]
    public function it_ignores_media_added_to_models_without_the_contract(): void
    {
        $media = $this->mediaFor(Item::factory()->create());
        Event::fake([RaidingDataChanged::class, GameVersionChanged::class]);

        $this->listener()->handleMediaAdded(new MediaHasBeenAddedEvent($media));
        $this->app->terminate();

        Event::assertNothingDispatched();
        $this->assertFalse($media->relationLoaded('model'));
    }

    #[Group('laravel-media-library')]
    #[Test]
    #[DataProvider('owners')]
    public function it_dispatches_once_when_a_collection_is_cleared(string $modelClass, string $eventClass, Closure $owner): void
    {
        $model = $modelClass::factory()->create();
        Event::fake([$eventClass]);

        $this->listener()->handleCollectionCleared(new CollectionHasBeenClearedEvent($model, 'banner'));
        $this->app->terminate();

        $this->assertSame(1, $this->dispatchedFor($eventClass, $owner, $model));
    }

    #[Group('laravel-media-library')]
    #[Test]
    public function it_ignores_collections_cleared_on_other_models(): void
    {
        $item = Item::factory()->create();
        Event::fake([RaidingDataChanged::class, GameVersionChanged::class]);

        $this->listener()->handleCollectionCleared(new CollectionHasBeenClearedEvent($item, 'icon'));

        Event::assertNothingDispatched();
    }

    #[Group('laravel-media-library')]
    #[Test]
    #[DataProvider('owners')]
    public function it_dispatches_once_when_a_conversion_completes(string $modelClass, string $eventClass, Closure $owner): void
    {
        $model = $modelClass::factory()->create();
        Event::fake([$eventClass]);

        $this->listener()->handleConversionCompleted($this->conversionCompletedFor($model));
        $this->app->terminate();

        $this->assertSame(1, $this->dispatchedFor($eventClass, $owner, $model));
    }

    #[Group('laravel-media-library')]
    #[Test]
    public function it_ignores_conversions_of_other_models(): void
    {
        $item = Item::factory()->create();
        Event::fake([RaidingDataChanged::class, GameVersionChanged::class]);

        $this->listener()->handleConversionCompleted($this->conversionCompletedFor($item));

        Event::assertNothingDispatched();
    }

    // ==================== helpers ====================

    private function listener(): DispatchMediaOwnerChanged
    {
        return $this->app->make(DispatchMediaOwnerChanged::class);
    }

    private function mediaFor(Model $model): Media
    {
        return (new Media)->forceFill([
            'model_type' => $model->getMorphClass(),
            'model_id' => $model->getKey(),
        ]);
    }

    private function conversionCompletedFor(Model $model): ConversionHasBeenCompletedEvent
    {
        return new ConversionHasBeenCompletedEvent($this->mediaFor($model), new Conversion('header'));
    }

    /**
     * @param  class-string  $eventClass
     */
    private function dispatchedFor(string $eventClass, Closure $owner, Model $model): int
    {
        return Event::dispatched($eventClass, fn (object $event): bool => $owner($event)->is($model))->count();
    }
}
