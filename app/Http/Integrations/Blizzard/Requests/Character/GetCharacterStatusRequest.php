<?php

namespace App\Http\Integrations\Blizzard\Requests\Character;

use App\Http\Integrations\Blizzard\BlizzardConnector;
use App\Http\Integrations\Blizzard\Concerns\HasCaching;
use App\Http\Integrations\Blizzard\Data\Characters\CharacterStatusData;
use Illuminate\Support\Str;
use Saloon\CachePlugin\Contracts\Cacheable;
use Saloon\Enums\Method;
use Saloon\Http\PendingRequest;
use Saloon\Http\Request;
use Saloon\Http\Response;

class GetCharacterStatusRequest extends Request implements Cacheable
{
    use HasCaching;

    protected Method $method = Method::GET;

    public function __construct(
        protected string $realmSlug,
        protected string $characterName,
    ) {}

    public function resolveEndpoint(): string
    {
        return sprintf(
            '/profile/wow/character/%s/%s/status',
            $this->realmSlug,
            Str::lower($this->characterName),
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
        return 21600;
    }

    public function createDtoFromResponse(Response $response): CharacterStatusData
    {
        return CharacterStatusData::from($response->json());
    }
}
