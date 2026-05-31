<?php

namespace App\Http\Integrations\Blizzard\Support;

use App\Http\Integrations\Blizzard\Region;
use Illuminate\Support\Stringable;

class MirrorPaths
{
    /**
     * Top-level directory under the configured disk where all mirrored Blizzard CDN assets are stored.
     * Namespacing under a single prefix keeps mirror files isolated from other application assets
     * and makes bulk operations (purge, audit) straightforward.
     */
    protected const PREFIX = 'blizzard-cdn';

    public function __construct(
        /**
         * The region context to resolve URLs against. This is required to correctly strip the optional region segment from the path.
         */
        protected readonly Region $region,
    ) {}

    /**
     * Convert a Blizzard CDN URL to a local mirror path, stripping the host and
     * optional region path prefix. Returns null for URLs outside the render host.
     *
     * Example: https://render.worldofwarcraft.com/eu/icons/56/foo.jpg → blizzard-cdn/icons/56/foo.jpg
     */
    public function fromUrl(string $url): ?string
    {
        $host = str(parse_url($url, PHP_URL_HOST));

        if (! $this->validateHost($host)) {
            return null;
        }

        $path = str(parse_url($url, PHP_URL_PATH) ?: '/')
            ->ltrim('/')
            ->after($this->region->value.'/')
            ->value();

        return self::PREFIX.'/'.$path;
    }

    /**
     * Determine whether a host belongs to the Blizzard render CDN.
     * Accepts both render.worldofwarcraft.com and regional subdomain variants (e.g. render-eu.worldofwarcraft.com).
     */
    protected function validateHost(Stringable $host): bool
    {
        return $host->is('render.worldofwarcraft.com') || ($host->startsWith('render-') && $host->endsWith('.worldofwarcraft.com'));
    }
}
