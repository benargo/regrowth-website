<?php

namespace App\Http\Integrations\Blizzard\Requests\Render;

use App\Contracts\HasBlizzardIcons;
use App\Http\Integrations\Blizzard\RenderConnector;
use App\Http\Integrations\Blizzard\Requests\RenderRequest;
use Illuminate\Support\Str;
use Illuminate\Support\Stringable;
use Illuminate\Support\Uri;
use Saloon\Http\PendingRequest;

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
class FetchIconRequest extends RenderRequest implements HasBlizzardIcons
{
    private ?string $iconName = null;

    public function __construct(Uri|string $input, ?int $size = null)
    {
        parent::__construct($input, $size);

        if (is_string($input)) {
            Str::of($input)->tap(function (Stringable $string) {
                if ($string->doesntContain('://')) {
                    $this->iconName = $string->value();
                }
            });
        }

        $this->size = $size ?? self::DEFAULT_MEDIA_SIZE;
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

        $iconName = Str::of($this->iconName);

        $iconName = $iconName->when($iconName->doesntContain('.'), function (Stringable $str) {
            return $str->append('.', self::DEFAULT_MEDIA_FILE_EXTENSION);
        });

        /** @var RenderConnector $connector */
        $connector = $pendingRequest->getConnector();

        $pendingRequest->setUrl(
            $connector->getRegion()->renderCdnUrl()."/icons/{$this->size}/{$iconName->value()}",
        );
    }
}
