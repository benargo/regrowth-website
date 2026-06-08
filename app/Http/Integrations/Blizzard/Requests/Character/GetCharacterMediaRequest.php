<?php

namespace App\Http\Integrations\Blizzard\Requests\Character;

use App\Http\Integrations\Blizzard\BlizzardConnector;
use App\Http\Integrations\Blizzard\Concerns\HasCaching;
use App\Http\Integrations\Blizzard\Data\Characters\CharacterMediaData;
use Illuminate\Support\Str;
use Saloon\CachePlugin\Contracts\Cacheable;
use Saloon\Enums\Method;
use Saloon\Http\PendingRequest;
use Saloon\Http\Request;
use Saloon\Http\Response;

class GetCharacterMediaRequest extends Request implements Cacheable
{
    use HasCaching;

    protected Method $method = Method::GET;

    public function __construct(
        protected string $realm,
        protected string $character,
    ) {
        $this->realm = Str::slug($realm);
        $this->character = Str::slug($character);
    }

    public function resolveEndpoint(): string
    {
        return sprintf('/profile/wow/character/%s/%s/character-media', $this->realm, $this->character);
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
        return 86400; // 24 hours
    }

    public function createDtoFromResponse(Response $response): CharacterMediaData
    {
        return CharacterMediaData::from($response->json());
    }
}
