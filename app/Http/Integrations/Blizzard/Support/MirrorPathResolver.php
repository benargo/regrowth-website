<?php

namespace App\Http\Integrations\Blizzard\Support;

use App\Http\Integrations\Blizzard\Region;
use Illuminate\Support\Str;

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

        if (! is_string($host) || ! $this->validateHost($host)) {
            return null;
        }

        $path = Str::of(parse_url($url, PHP_URL_PATH) ?: '/')
            ->ltrim('/')
            ->after($this->region->value.'/')
            ->value();

        return self::PREFIX.'/'.$path;
    }

    /**
     * Determine whether a host belongs to the Blizzard render CDN.
     * Accepts both render.worldofwarcraft.com and regional subdomain variants (e.g. render-eu.worldofwarcraft.com).
     */
    public function validateHost(string $host): bool
    {
        $host = Str::of($host);

        return $host->is('render.worldofwarcraft.com') || ($host->startsWith('render-') && $host->endsWith('.worldofwarcraft.com'));
    }
}
