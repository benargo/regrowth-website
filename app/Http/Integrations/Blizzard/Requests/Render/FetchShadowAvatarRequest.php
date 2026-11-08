<?php

namespace App\Http\Integrations\Blizzard\Requests\Render;

use App\Contracts\HasShadowAvatars;
use App\Enums\Gender;
use App\Http\Integrations\Blizzard\RenderConnector;
use App\Http\Integrations\Blizzard\Requests\RenderRequest;
use Saloon\Http\PendingRequest;

/**
 * Fetches the generic shadow avatar for a playable race and gender from the
 * Blizzard render CDN, e.g. `{renderCdnUrl}/shadow/avatar/6-0.jpg` for a male Tauren.
 * The region segment comes from the connector.
 */
class FetchShadowAvatarRequest extends RenderRequest implements HasShadowAvatars
{
    public function __construct(
        private readonly int $playableRaceId,
        private readonly Gender $gender,
    ) {}

    /**
     * Replace the placeholder endpoint with the shadow avatar URL for the connector's region.
     */
    public function boot(PendingRequest $pendingRequest): void
    {
        /** @var RenderConnector $connector */
        $connector = $pendingRequest->getConnector();

        $pendingRequest->setUrl(
            "{$connector->getRegion()->renderCdnUrl()}/shadow/avatar/{$this->fileName()}",
        );
    }

    /**
     * The shadow avatar's file name, e.g. `6-0.jpg`.
     */
    public function fileName(): string
    {
        return "{$this->playableRaceId}-{$this->gender->id()}.".self::DEFAULT_MEDIA_FILE_EXTENSION;
    }
}
