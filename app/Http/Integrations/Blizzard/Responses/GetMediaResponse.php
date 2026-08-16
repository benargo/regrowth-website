<?php

namespace App\Http\Integrations\Blizzard\Responses;

use App\Http\Integrations\Blizzard\Data\Media\MediaData;
use App\Http\Integrations\Blizzard\RenderConnector;
use App\Http\Integrations\Blizzard\Requests\Render\FetchIconRequest;
use Saloon\Http\Response;
use Throwable;

/**
 * Custom Saloon response for lazy-mirrored media requests.
 *
 * Overrides dto() to memoize the MediaData DTO so that AssetData instances
 * annotated by mirror() are preserved across multiple dto() calls.
 */
class GetMediaResponse extends Response
{
    /** @var MediaData|null Memoized DTO — populated on first dto() call. */
    private ?MediaData $mediaData = null;

    /**
     * Deserialize the response body into a memoized MediaData DTO.
     *
     * Memoization ensures that AssetData instances enriched by mirror() are
     * not discarded if dto() is called more than once.
     */
    public function dto(): MediaData
    {
        return $this->mediaData ??= MediaData::from($this->json());
    }

    /**
     * Send a FetchIconRequest for each asset in the DTO via the given
     * RenderConnector and annotate each AssetData with its mirrored path.
     *
     * Errors for individual assets are reported and swallowed so a single
     * failed fetch does not abort the remaining assets.
     *
     * @return static Fluent — returns $this for chaining.
     */
    public function mirror(RenderConnector $connector): static
    {
        foreach ($this->dto()->assets as $asset) {
            try {
                /** @var FetchAssetResponse $fetchResponse */
                $fetchResponse = $connector->send(new FetchIconRequest($asset->value));
                $mirroredPath = $fetchResponse->mirroredPath();

                if ($mirroredPath !== null) {
                    $asset->setMirroredPath($mirroredPath);
                }
            } catch (Throwable $e) {
                report($e);
            }
        }

        return $this;
    }
}
