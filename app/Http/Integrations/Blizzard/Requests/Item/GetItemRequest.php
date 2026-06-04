<?php

namespace App\Http\Integrations\Blizzard\Requests\Item;

use App\Http\Integrations\Blizzard\BlizzardConnector;
use App\Http\Integrations\Blizzard\Concerns\HasCaching;
use App\Http\Integrations\Blizzard\Data\Item\ItemData;
use Saloon\CachePlugin\Contracts\Cacheable;
use Saloon\Enums\Method;
use Saloon\Http\PendingRequest;
use Saloon\Http\Request;
use Saloon\Http\Response;

class GetItemRequest extends Request implements Cacheable
{
    use HasCaching;

    protected Method $method = Method::GET;

    public function __construct(
        protected int $itemId,
    ) {}

    public function resolveEndpoint(): string
    {
        return "/data/wow/item/{$this->itemId}";
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
        return 2628000; // ~30 days
    }

    public function createDtoFromResponse(Response $response): ItemData
    {
        return ItemData::from($response->json());
    }
}
