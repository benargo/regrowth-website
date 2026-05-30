<?php

namespace App\Http\Integrations\Blizzard;

use App\Http\Integrations\Blizzard\Middleware\ServeMirroredAsset;
use App\Http\Integrations\Blizzard\Middleware\WriteMirrorToDisk;
use App\Http\Integrations\Blizzard\Support\MirrorPathResolver;
use Illuminate\Contracts\Filesystem\Filesystem;
use Saloon\Http\Connector;

/**
 * Saloon connector for the Blizzard render CDN (render.worldofwarcraft.com).
 *
 * Returns raw binary asset bodies. Unauthenticated and intentionally does not
 * use AlwaysThrowOnErrors — WriteMirrorToDisk is responsible for 404 → MediaNotFoundException
 * translation; other status codes are swallowed or reported there.
 */
class RenderConnector extends Connector
{
    public function __construct(
        /**
         * Region is retained for middleware wiring (e.g. MirrorPathResolver) even though it is
         * no longer part of the base URL. Real Blizzard render CDN URLs mix apex and region-path
         * shapes, so the connector returns a region-agnostic base and lets the caller's path
         * (via FetchAssetRequest) preserve whichever region segment the original URL carried.
         */
        protected Region $region,
        Filesystem $disk,
    ) {
        $resolver = new MirrorPathResolver($region);

        $this->middleware()->onRequest(
            new ServeMirroredAsset($resolver, $disk),
            'serveMirroredAsset',
        );

        $this->middleware()->onResponse(
            new WriteMirrorToDisk,
            'writeMirrorToDisk',
        );
    }

    /**
     * Region-agnostic base URL for the Blizzard render CDN. Region segments, when present in
     * source URLs, are carried through the request endpoint rather than the connector base.
     */
    public function resolveBaseUrl(): string
    {
        return 'https://render.worldofwarcraft.com';
    }

    /**
     * Get the configured region.
     */
    public function getRegion(): Region
    {
        return $this->region;
    }
}
