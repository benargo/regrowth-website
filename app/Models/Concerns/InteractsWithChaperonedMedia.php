<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Attaches media to a model, setting each media item's `model` relation to the
 * host, and reports the MIME types each media collection accepts.
 */
trait InteractsWithChaperonedMedia
{
    use InteractsWithMedia;

    /**
     * Get the media attached to the model, each with its `model` relation set to the model.
     *
     * @return MorphMany<Media, $this>
     */
    public function media(): MorphMany
    {
        return $this->morphMany($this->getMediaModel(), 'model')->chaperone('model');
    }

    /**
     * Get the MIME types a media collection accepts.
     *
     * @return list<string>
     */
    public function acceptedMimeTypes(string $collection): array
    {
        return $this->getRegisteredMediaCollections()->firstWhere('name', $collection)->acceptsMimeTypes ?? [];
    }
}
