<?php

namespace App\Http\Integrations\Blizzard\Requests\Render;

use App\Contracts\HasCharacterMedia;
use App\Http\Integrations\Blizzard\RenderConnector;
use App\Http\Integrations\Blizzard\Requests\RenderRequest;
use Illuminate\Support\Str;
use Illuminate\Support\Stringable;
use Illuminate\Support\Uri;
use InvalidArgumentException;
use Saloon\Http\PendingRequest;

/**
 * Fetches a character media asset (portrait, avatar, render, etc.) from the Blizzard render CDN.
 *
 * Accepts either a full asset URL (as returned by the Blizzard character media API)
 * or a bare media path in `{realmSlug}/{mediaFile}` format
 * (e.g. `thunderstrike/51042439-avatar`). When given a bare path, the full URL
 * is constructed in boot() using the connector's configured region.
 *
 * When given an absolute URL, the host is stripped and the host-relative path is
 * used as the endpoint, avoiding the SSRF-flavoured opt-in for absolute-URL
 * endpoint overrides.
 */
class FetchCharacterMediaRequest extends RenderRequest implements HasCharacterMedia
{
    private ?string $realmSlug = null;

    private ?string $mediaPath = null;

    public function __construct(Uri|string $input, ?int $size = null)
    {
        parent::__construct($input, $size);

        if (is_string($input)) {
            Str::of($input)->tap(function (Stringable $string) {
                if ($string->doesntContain('://')) {
                    if ($string->doesntContain('/')) {
                        throw new InvalidArgumentException(
                            "FetchCharacterMediaRequest bare input must be in {realmSlug}/{mediaFile} format; got: {$string}",
                        );
                    }

                    [$realmSlug, $mediaPath] = explode('/', $string->value(), 2);
                    $this->realmSlug = $realmSlug;
                    $this->mediaPath = $mediaPath;
                }
            });
        }

        $this->size = $size ?? self::DEFAULT_MEDIA_SIZE;
    }

    /**
     * Resolve the region from the connector and overwrite the pending URL with the media path.
     * Only runs for bare-input mode; absolute-URL inputs already set $endpoint in the constructor.
     *
     * boot() runs after PendingRequest::__construct() locks in the URL from resolveEndpoint(),
     * so we use setUrl() to replace the placeholder with the fully-resolved media URL.
     */
    public function boot(PendingRequest $pendingRequest): void
    {
        if ($this->realmSlug === null) {
            return;
        }

        $mediaPath = Str::of($this->mediaPath);

        $mediaPath = $mediaPath->when($mediaPath->doesntContain('.'), function (Stringable $str) {
            return $str->append('.', self::DEFAULT_MEDIA_FILE_EXTENSION);
        });

        /** @var RenderConnector $connector */
        $connector = $pendingRequest->getConnector();

        $pendingRequest->setUrl(
            $connector->getRegion()->renderCdnUrl()."/character/{$this->realmSlug}/{$this->size}/{$mediaPath->value()}",
        );
    }
}
