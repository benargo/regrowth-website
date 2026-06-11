<?php

namespace App\Http\Integrations\RaidHelper\Requests;

use App\Http\Integrations\RaidHelper\Concerns\HasCaching;
use App\Http\Integrations\RaidHelper\Data\Events\EventData;
use Saloon\CachePlugin\Contracts\Cacheable;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;

class GetEventRequest extends Request implements Cacheable
{
    use HasCaching;

    protected Method $method = Method::GET;

    public function __construct(
        protected string $eventId,
    ) {}

    public function resolveEndpoint(): string
    {
        return "/events/{$this->eventId}";
    }

    public function cacheExpiryInSeconds(): int
    {
        return 60;
    }

    public function createDtoFromResponse(Response $response): EventData
    {
        return EventData::from($response->json());
    }
}
