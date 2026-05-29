<?php

namespace App\Http\Integrations\Blizzard;

use Saloon\Http\Connector;

/**
 * Saloon connector for the Blizzard render CDN (render.worldofwarcraft.com).
 *
 * Returns raw binary asset bodies. Unauthenticated and intentionally does not
 * use AlwaysThrowOnErrors — the BlizzardMediaMirror is responsible for
 * tolerating failed downloads and falling back to null.
 */
class RenderConnector extends Connector
{
    public function __construct(
        /**
         * Region determines the base URL for asset requests, e.g. https://eu.render.worldofwarcraft.com for EU.
         */
        protected Region $region,
    ) {}

    /**
     * The base URL is determined by the region, e.g. https://eu.render.worldofwarcraft.com for EU.
     */
    public function resolveBaseUrl(): string
    {
        return $this->region->renderCdnUrl();
    }

    /**
     * Get the configured region.
     */
    public function getRegion(): Region
    {
        return $this->region;
    }
}
