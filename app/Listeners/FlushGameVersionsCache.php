<?php

namespace App\Listeners;

use App\Contracts\Actions\GameVersion\CachesSelectableGameVersions;
use App\Contracts\Events\FlushesGameVersionsCache;
use App\Models\GameVersion;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Spatie\MediaLibrary\MediaCollections\Events\CollectionHasBeenClearedEvent;
use Spatie\MediaLibrary\MediaCollections\Events\MediaHasBeenAddedEvent;

/**
 * Flushes the selectable game versions cache and the list memoised for the current request.
 */
class FlushGameVersionsCache implements CachesSelectableGameVersions
{
    public function __construct(private Application $app, private Request $request) {}

    /**
     * Handle the event.
     */
    public function handle(FlushesGameVersionsCache $event): void
    {
        $this->flush();
    }

    /**
     * Flush the cache when the media belongs to a game version, and again
     * when the application terminates.
     */
    public function handleMediaAdded(MediaHasBeenAddedEvent $event): void
    {
        if ($event->media->model_type === (new GameVersion)->getMorphClass()) {
            $this->flush();
            $this->app->terminating(fn () => $this->flush());
        }
    }

    /**
     * Flush the cache when the collection belongs to a game version.
     */
    public function handleCollectionCleared(CollectionHasBeenClearedEvent $event): void
    {
        if ($event->model instanceof GameVersion) {
            $this->flush();
        }
    }

    private function flush(): void
    {
        Cache::tags([self::CACHE_TAG])->flush();
        $this->request->attributes->remove(self::REQUEST_KEY);
    }
}
