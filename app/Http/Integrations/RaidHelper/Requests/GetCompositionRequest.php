<?php

namespace App\Http\Integrations\RaidHelper\Requests;

use App\Http\Integrations\RaidHelper\Concerns\CachesRaidHelperResponses;
use App\Http\Integrations\RaidHelper\Data\Compositions\CompositionData;
use Saloon\CachePlugin\Contracts\Cacheable;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;

class GetCompositionRequest extends Request implements Cacheable
{
    use CachesRaidHelperResponses;

    protected Method $method = Method::GET;

    public function __construct(
        protected string $eventId,
    ) {}

    public function resolveEndpoint(): string
    {
        return "/comps/{$this->eventId}";
    }

    public function cacheExpiryInSeconds(): int
    {
        return 60;
    }

    public function createDtoFromResponse(Response $response): CompositionData
    {
        return CompositionData::from($response->json());
    }
}
