<?php

namespace App\Http\Integrations\Blizzard\Requests\Render;

use App\Http\Integrations\Blizzard\Responses\FetchAssetResponse;
use Illuminate\Support\Stringable;
use InvalidArgumentException;
use Saloon\Enums\Method;
use Saloon\Http\Request;

/**
 * Fetches an arbitrary asset from the Blizzard render CDN.
 *
 * The constructor takes the full asset URL (as it appears in Blizzard API payloads)
 * and reduces it to a host-relative endpoint so it composes against RenderConnector's
 * base URL like any other Saloon request — avoiding the SSRF-flavoured opt-in for
 * absolute-URL endpoint overrides.
 */
class FetchAssetRequest extends Request
{
    protected Method $method = Method::GET;

    protected ?string $response = FetchAssetResponse::class;

    private readonly string $endpoint;

    /**
     * Initialize the request with a full asset URL, validating that it belongs to the Blizzard render CDN
     * and extracting the host-relative path for use as the request endpoint.
     *
     * @throws InvalidArgumentException if the URL is invalid or does not belong to the Blizzard render CDN
     */
    public function __construct(string $absoluteUrl)
    {
        $host = str(parse_url($absoluteUrl, PHP_URL_HOST));

        if (! $this->validateHost($host)) {
            throw new InvalidArgumentException(
                "FetchAssetRequest requires a Blizzard render URL; got: {$absoluteUrl}",
            );
        }

        $path = str(parse_url($absoluteUrl, PHP_URL_PATH));

        if (! $this->validatePath($path)) {
            throw new InvalidArgumentException(
                "FetchAssetRequest requires a URL with a non-empty path; got: {$absoluteUrl}",
            );
        }

        $this->endpoint = $path->start('/');
    }

    /**
     * Return the host-relative path extracted from the original asset URL.
     */
    public function resolveEndpoint(): string
    {
        return $this->endpoint;
    }

    /**
     * Determine whether a host belongs to the Blizzard render CDN.
     * Accepts both render.worldofwarcraft.com and regional subdomain variants (e.g. render-eu.worldofwarcraft.com).
     */
    protected function validateHost(Stringable $host): bool
    {
        return $host->is('render.worldofwarcraft.com') || ($host->startsWith('render-') && $host->endsWith('.worldofwarcraft.com'));
    }

    /**
     * Validate that the URL path is non-empty and not just a slash, which would be invalid for asset URLs
     * and indicate a likely error in URL parsing or construction.
     */
    protected function validatePath(Stringable $path): bool
    {
        return ! $path->isEmpty() && ! $path->is('/');
    }
}
