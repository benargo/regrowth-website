<?php

namespace App\Http\Integrations\Blizzard\Requests\Render;

use App\Contracts\HasBlizzardIcons;
use App\Http\Integrations\Blizzard\RenderConnector;
use App\Http\Integrations\Blizzard\Responses\FetchAssetResponse;
use Illuminate\Support\Stringable;
use InvalidArgumentException;
use Saloon\Enums\Method;
use Saloon\Http\PendingRequest;
use Saloon\Http\Request;

/**
 * Fetches an arbitrary asset from the Blizzard render CDN.
 *
 * Accepts either a full asset URL (as it appears in Blizzard API payloads) or a
 * bare icon name (e.g. `inv_misc_questionmark`). When given an icon name, the
 * full URL is constructed in boot() using the connector's configured region so
 * the correct region segment is always used.
 *
 * When given an absolute URL, the host is stripped and the host-relative path is
 * used as the endpoint, avoiding the SSRF-flavoured opt-in for absolute-URL
 * endpoint overrides.
 */
class FetchIconRequest extends Request implements HasBlizzardIcons
{
    protected Method $method = Method::GET;

    protected ?string $response = FetchAssetResponse::class;

    private ?string $endpoint = null;

    private readonly ?string $iconName;

    private readonly int $size;

    /**
     * @throws InvalidArgumentException if an absolute URL is given that is invalid
     *                                  or does not belong to the Blizzard render CDN
     */
    public function __construct(string $input, int $size = self::DEFAULT_MEDIA_SIZE)
    {
        if (str_contains($input, '://')) {
            $host = str(parse_url($input, PHP_URL_HOST));

            if (! $this->validateHost($host)) {
                throw new InvalidArgumentException(
                    "FetchIconRequest requires a Blizzard render URL; got: {$input}",
                );
            }

            $path = str(parse_url($input, PHP_URL_PATH));

            if (! $this->validatePath($path)) {
                throw new InvalidArgumentException(
                    "FetchIconRequest requires a URL with a non-empty path; got: {$input}",
                );
            }

            $this->endpoint = $path->start('/');
            $this->iconName = null;
            $this->size = $size;
        } else {
            $this->iconName = $input;
            $this->size = $size;
        }
    }

    /**
     * Resolve the region from the connector and overwrite the pending URL with the icon path.
     * Only runs for icon-name inputs; absolute-URL inputs already set $endpoint in the constructor.
     *
     * boot() runs after PendingRequest::__construct() locks in the URL from resolveEndpoint(),
     * so we use setUrl() to replace the placeholder with the fully-resolved icon URL.
     */
    public function boot(PendingRequest $pendingRequest): void
    {
        if ($this->iconName === null) {
            return;
        }

        $iconName = str($this->iconName)->contains('.') ? $this->iconName : "{$this->iconName}.jpg";

        /** @var RenderConnector $connector */
        $connector = $pendingRequest->getConnector();

        $pendingRequest->setUrl(
            $connector->getRegion()->renderCdnUrl()."/icons/{$this->size}/{$iconName}",
        );
    }

    /**
     * Return the host-relative path for this asset request.
     * For icon-name inputs, boot() overwrites the URL via setUrl() after construction.
     */
    public function resolveEndpoint(): string
    {
        return $this->endpoint ?? '/';
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
     * Validate that the URL path is non-empty and not just a slash.
     */
    protected function validatePath(Stringable $path): bool
    {
        return ! $path->isEmpty() && ! $path->is('/');
    }
}
