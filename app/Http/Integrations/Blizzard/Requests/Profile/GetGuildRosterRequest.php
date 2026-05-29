<?php

namespace App\Http\Integrations\Blizzard\Requests\Profile;

use App\Http\Integrations\Blizzard\BlizzardConnector;
use App\Http\Integrations\Blizzard\Concerns\HasCaching;
use App\Http\Integrations\Blizzard\Data\Guild\GuildRosterData;
use Saloon\CachePlugin\Contracts\Cacheable;
use Saloon\Enums\Method;
use Saloon\Http\PendingRequest;
use Saloon\Http\Request;
use Saloon\Http\Response;

class GetGuildRosterRequest extends Request implements Cacheable
{
    use HasCaching;

    protected Method $method = Method::GET;

    public function __construct(
        protected string $realmSlug,
        protected string $guildSlug,
    ) {}

    public function resolveEndpoint(): string
    {
        return sprintf(
            '/data/wow/guild/%s/%s/roster',
            $this->realmSlug,
            $this->guildSlug,
        );
    }

    public function boot(PendingRequest $pendingRequest): void
    {
        /** @var BlizzardConnector $connector */
        $connector = $pendingRequest->getConnector();

        $pendingRequest->headers()->add(
            'Battlenet-Namespace',
            $connector->namespace('profile'),
        );
    }

    public function cacheExpiryInSeconds(): int
    {
        return 900;
    }

    public function createDtoFromResponse(Response $response): GuildRosterData
    {
        return GuildRosterData::from($response->json());
    }
}
