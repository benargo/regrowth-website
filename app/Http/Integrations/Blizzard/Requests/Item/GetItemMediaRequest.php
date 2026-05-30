<?php

namespace App\Http\Integrations\Blizzard\Requests\Item;

use App\Http\Integrations\Blizzard\Attributes\EagerlyMirrorsAssets;
use App\Http\Integrations\Blizzard\BlizzardConnector;
use App\Http\Integrations\Blizzard\Concerns\HasCaching;
use App\Http\Integrations\Blizzard\Data\Media\MediaData;
use Saloon\CachePlugin\Contracts\Cacheable;
use Saloon\Enums\Method;
use Saloon\Http\PendingRequest;
use Saloon\Http\Request;
use Saloon\Http\Response;

#[EagerlyMirrorsAssets]
class GetItemMediaRequest extends Request implements Cacheable
{
    use HasCaching;

    protected Method $method = Method::GET;

    public function __construct(
        protected int $itemId,
    ) {}

    public function resolveEndpoint(): string
    {
        return "/data/wow/media/item/{$this->itemId}";
    }

    public function boot(PendingRequest $pendingRequest): void
    {
        /** @var BlizzardConnector $connector */
        $connector = $pendingRequest->getConnector();

        $pendingRequest->headers()->add(
            'Battlenet-Namespace',
            $connector->namespace('static'),
        );
    }

    public function cacheExpiryInSeconds(): int
    {
        return 604800; // 7 days
    }

    public function createDtoFromResponse(Response $response): MediaData
    {
        return MediaData::from($response->json());
    }
}
