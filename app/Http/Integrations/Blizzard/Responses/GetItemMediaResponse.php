<?php

namespace App\Http\Integrations\Blizzard\Responses;

use App\Http\Integrations\Blizzard\Data\Media\MediaData;
use Saloon\Http\Response;

/**
 * Custom Saloon response for eagerly-mirrored item media requests.
 *
 * Overrides dto() to memoize the MediaData DTO so that AssetData instances
 * annotated by EagerlyMirrorAssets middleware are preserved across multiple
 * dto() calls.
 */
class GetItemMediaResponse extends Response
{
    /** @var MediaData|null Memoized DTO — populated on first dto() call. */
    private ?MediaData $mediaData = null;

    /**
     * Deserialize the response body into a memoized MediaData DTO.
     *
     * Memoization ensures that AssetData instances enriched by the
     * EagerlyMirrorAssets middleware are not discarded if dto() is called
     * more than once.
     */
    public function dto(): MediaData
    {
        return $this->mediaData ??= MediaData::from($this->json());
    }
}
