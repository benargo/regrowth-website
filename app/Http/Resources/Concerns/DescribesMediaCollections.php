<?php

namespace App\Http\Resources\Concerns;

use Spatie\MediaLibrary\MediaCollections\MediaCollection;

/**
 * For resources wrapping a model with media: describes the MIME types each of
 * the model's media collections accepts.
 */
trait DescribesMediaCollections
{
    /**
     * The MIME types each media collection accepts, keyed by collection name.
     *
     * @return array<string, array{accepts: list<string>}>
     */
    protected function mediaCollections(): array
    {
        return $this->getRegisteredMediaCollections()
            ->mapWithKeys(fn (MediaCollection $collection): array => [
                $collection->name => ['accepts' => $collection->acceptsMimeTypes],
            ])
            ->all();
    }
}
