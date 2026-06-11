<?php

namespace App\Http\Integrations\RaidHelper\Requests;

use App\Http\Integrations\RaidHelper\Concerns\HasCaching;
use App\Http\Integrations\RaidHelper\Data\Events\EventData;
use Carbon\CarbonInterface;
use Saloon\CachePlugin\Contracts\Cacheable;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\PaginationPlugin\Contracts\Paginatable;

class GetEventsRequest extends Request implements Cacheable, Paginatable
{
    use HasCaching;

    protected Method $method = Method::GET;

    public function __construct(
        protected string $serverId,
        protected bool $includeSignUps = false,
        protected ?string $channelId = null,
        protected ?CarbonInterface $startTimeFilter = null,
        protected ?CarbonInterface $endTimeFilter = null,
    ) {}

    public function resolveEndpoint(): string
    {
        return "/servers/{$this->serverId}/events";
    }

    /**
     * The listing filters travel as request headers. `Page` is injected per-page by
     * the connector's paginator, so it is intentionally absent here.
     *
     * @return array<string, mixed>
     */
    protected function defaultHeaders(): array
    {
        $headers = [];

        if ($this->includeSignUps) {
            $headers['IncludeSignUps'] = 'true';
        }

        if ($this->channelId !== null) {
            $headers['ChannelFilter'] = $this->channelId;
        }

        if ($this->startTimeFilter !== null) {
            $headers['StartTimeFilter'] = $this->startTimeFilter->utc()->unix();
        }

        if ($this->endTimeFilter !== null) {
            $headers['EndTimeFilter'] = $this->endTimeFilter->utc()->unix();
        }

        return $headers;
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
