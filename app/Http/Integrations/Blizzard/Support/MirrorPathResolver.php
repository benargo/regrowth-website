<?php

namespace App\Http\Integrations\Blizzard\Support;

use App\Http\Integrations\Blizzard\Region;

class MirrorPathResolver
{
    /**
     * Top-level directory under the configured disk where all mirrored Blizzard CDN assets are stored.
     * Namespacing under a single prefix keeps mirror files isolated from other application assets
     * and makes bulk operations (purge, audit) straightforward.
     */
    private const PREFIX = 'blizzard-cdn';

    public function __construct(
        private readonly Region $region,
    ) {}

    /**
     * Convert a Blizzard CDN URL to a local mirror path, stripping the host and
     * optional region path prefix. Returns null for URLs outside the render host.
     *
     * Example: https://render.worldofwarcraft.com/eu/icons/56/foo.jpg → blizzard-cdn/icons/56/foo.jpg
     */
    public function fromUrl(string $url): ?string
    {
        $host = parse_url($url, PHP_URL_HOST);

        if (! is_string($host) || ! $this->isRenderHost($host)) {
            return null;
        }

        $path = parse_url($url, PHP_URL_PATH) ?: '/';
        $path = ltrim($path, '/');

        $regionPrefix = $this->region->value.'/';
        if (str_starts_with($path, $regionPrefix)) {
            $path = substr($path, strlen($regionPrefix));
        }

        return self::PREFIX.'/'.$path;
    }

    /**
     * Determine whether a host belongs to the Blizzard render CDN.
     * Accepts both render.worldofwarcraft.com and regional subdomain variants (e.g. render-eu.worldofwarcraft.com).
     */
    private function isRenderHost(string $host): bool
    {
        return $host === 'render.worldofwarcraft.com'
            || str_ends_with($host, '.worldofwarcraft.com');
    }
}
