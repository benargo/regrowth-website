<?php

namespace App\Listeners;

use App\Contracts\Actions\GameVersion\CachesSelectableGameVersions;
use App\Contracts\Events\FlushesGameVersionsCache;
use App\Models\GameVersion;
use Illuminate\Support\Facades\Cache;
use Spatie\MediaLibrary\MediaCollections\Events\CollectionHasBeenClearedEvent;
use Spatie\MediaLibrary\MediaCollections\Events\MediaHasBeenAddedEvent;

/**
 * Flushes the selectable game versions cache and the list memoised for the current request.
 */
class FlushGameVersionsCache
{
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
            app()->terminating(fn () => $this->flush());
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
        Cache::tags([CachesSelectableGameVersions::CACHE_TAG])->flush();
        request()->attributes->remove(CachesSelectableGameVersions::REQUEST_KEY);
    }
}
