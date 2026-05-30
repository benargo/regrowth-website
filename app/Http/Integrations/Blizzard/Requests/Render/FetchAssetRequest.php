<?php

namespace App\Http\Integrations\Blizzard\Requests\Render;

use App\Facades\BlizzardRenderPath;
use Illuminate\Support\Str;
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

    private readonly string $endpoint;

    public function __construct(string $absoluteUrl)
    {
        $host = parse_url($absoluteUrl, PHP_URL_HOST);

        if (! is_string($host) || ! BlizzardRenderPath::validateHost($host)) {
            throw new InvalidArgumentException(
                "FetchAssetRequest requires a Blizzard render URL; got: {$absoluteUrl}",
            );
        }

        $path = parse_url($absoluteUrl, PHP_URL_PATH);

        if (! is_string($path) || $path === '' || $path === '/') {
            throw new InvalidArgumentException(
                "FetchAssetRequest requires a URL with a non-empty path; got: {$absoluteUrl}",
            );
        }

        $this->endpoint = Str::start($path, '/');
    }

    /**
     * Return the host-relative path extracted from the original asset URL.
     */
    public function resolveEndpoint(): string
    {
        return $this->endpoint;
    }
}
