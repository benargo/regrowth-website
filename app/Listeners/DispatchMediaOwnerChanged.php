<?php

namespace App\Listeners;

use App\Contracts\Http\Middleware\SharesDeferredCacheFlushes;
use App\Contracts\Models\DispatchesChangesForMedia;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Spatie\MediaLibrary\Conversions\Events\ConversionHasBeenCompletedEvent;
use Spatie\MediaLibrary\MediaCollections\Events\CollectionHasBeenClearedEvent;
use Spatie\MediaLibrary\MediaCollections\Events\MediaHasBeenAddedEvent;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Dispatches the owning model's change event when media-library media is added, cleared or converted.
 */
class DispatchMediaOwnerChanged implements SharesDeferredCacheFlushes
{
    public function __construct(private Application $app, private Request $request) {}

    /**
     * Dispatch the owner's event now and, unless the request defers cache
     * flushes, once more per owner when the application terminates.
     */
    public function handleMediaAdded(MediaHasBeenAddedEvent $event): void
    {
        $owner = $this->ownerOf($event->media);

        if ($owner === null) {
            return;
        }

        $this->dispatchFor($owner);

        if ($this->request->attributes->get(self::DEFERRING_KEY)) {
            return;
        }

        $key = "media-owner-changed:{$owner->getMorphClass()}:{$owner->getKey()}";

        if (! $this->request->attributes->has($key)) {
            $this->request->attributes->set($key, true);
            $this->app->terminating(static fn () => $owner->mediaChangedEvent()::dispatch($owner));
        }
    }

    /**
     * Dispatch the owner's event when one of its collections is cleared.
     */
    public function handleCollectionCleared(CollectionHasBeenClearedEvent $event): void
    {
        if ($event->model instanceof DispatchesChangesForMedia) {
            $this->dispatchFor($event->model);
        }
    }

    /**
     * Dispatch the owner's event when a conversion of its media has been generated.
     */
    public function handleConversionCompleted(ConversionHasBeenCompletedEvent $event): void
    {
        $owner = $this->ownerOf($event->media);

        if ($owner !== null) {
            $this->dispatchFor($owner);
        }
    }

    /**
     * The model the media belongs to, or null without loading it when its class does not implement the contract.
     */
    private function ownerOf(Media $media): (DispatchesChangesForMedia&Model)|null
    {
        $class = Relation::getMorphedModel($media->model_type) ?? $media->model_type;

        if (! is_a($class, DispatchesChangesForMedia::class, true)) {
            return null;
        }

        return $media->model;
    }

    private function dispatchFor(DispatchesChangesForMedia&Model $owner): void
    {
        $owner->mediaChangedEvent()::dispatch($owner);
    }
}
