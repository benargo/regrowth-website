<?php

namespace App\Http\Integrations\Blizzard\Requests\PlayableClass;

use App\Http\Integrations\Blizzard\BlizzardConnector;
use App\Http\Integrations\Blizzard\Concerns\HasCaching;
use App\Http\Integrations\Blizzard\Data\Shared\LinkData;
use Saloon\CachePlugin\Contracts\Cacheable;
use Saloon\Enums\Method;
use Saloon\Http\PendingRequest;
use Saloon\Http\Request;
use Saloon\Http\Response;

class GetPlayableClassIndexRequest extends Request implements Cacheable
{
    use HasCaching;

    protected Method $method = Method::GET;

    public function resolveEndpoint(): string
    {
        return '/data/wow/playable-class/index';
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
        return 2592000; // 30 days
    }

    public function createDtoFromResponse(Response $response): array
    {
        return array_map(
            fn (array $class) => LinkData::from($class),
            $response->json('classes', []),
        );
    }
}
