<?php

namespace App\Http\Integrations\Blizzard\Responses;

use App\Http\Integrations\Blizzard\Data\PlayableClass\PlayableClassMediaData;
use Saloon\Http\Response;

/**
 * Custom Saloon response for eagerly-mirrored playable-class media requests.
 *
 * Overrides dto() to memoize the PlayableClassMediaData DTO so that AssetData
 * instances annotated by EagerlyMirrorAssets middleware are preserved across
 * multiple dto() calls.
 */
class GetPlayableClassMediaResponse extends Response
{
    /** @var PlayableClassMediaData|null Memoized DTO — populated on first dto() call. */
    private ?PlayableClassMediaData $playableClassMediaData = null;

    /**
     * Deserialize the response body into a memoized PlayableClassMediaData DTO.
     *
     * Memoization ensures that AssetData instances enriched by the
     * EagerlyMirrorAssets middleware are not discarded if dto() is called
     * more than once.
     */
    public function dto(): PlayableClassMediaData
    {
        return $this->playableClassMediaData ??= PlayableClassMediaData::from($this->json());
    }
}
