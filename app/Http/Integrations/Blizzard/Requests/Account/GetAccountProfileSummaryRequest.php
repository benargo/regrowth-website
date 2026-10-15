<?php

namespace App\Http\Integrations\Blizzard\Requests\Account;

use App\Http\Integrations\Blizzard\BlizzardConnector;
use App\Http\Integrations\Blizzard\BlizzardNamespace;
use App\Http\Integrations\Blizzard\Concerns\CachesBlizzardResponses;
use App\Http\Integrations\Blizzard\Data\Account\AccountProfileSummaryData;
use Saloon\CachePlugin\Contracts\Cacheable;
use Saloon\Contracts\Authenticator;
use Saloon\Enums\Method;
use Saloon\Http\Auth\TokenAuthenticator;
use Saloon\Http\PendingRequest;
use Saloon\Http\Request;
use Saloon\Http\Response;
use SensitiveParameter;

/**
 * Fetch the WoW profile summary for the Battle.net account that owns the
 * given user access token (Authorization Code Flow, `wow.profile` scope).
 */
class GetAccountProfileSummaryRequest extends Request implements Cacheable
{
    use CachesBlizzardResponses;

    protected Method $method = Method::GET;

    public function __construct(
        #[SensitiveParameter]
        protected string $accessToken,
        protected ?BlizzardNamespace $namespace = null,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/profile/user/wow';
    }

    protected function defaultAuth(): Authenticator
    {
        return new TokenAuthenticator($this->accessToken);
    }

    public function boot(PendingRequest $pendingRequest): void
    {
        $pendingRequest->headers()->add(
            'Battlenet-Namespace',
            $this->namespaceHeader($pendingRequest),
        );
    }

    public function cacheExpiryInSeconds(): int
    {
        return 3600;
    }

    public function createDtoFromResponse(Response $response): AccountProfileSummaryData
    {
        return AccountProfileSummaryData::from($response->json());
    }

    private function namespaceHeader(PendingRequest $pendingRequest): string
    {
        /** @var BlizzardConnector $connector */
        $connector = $pendingRequest->getConnector();

        return ($this->namespace ?? BlizzardNamespace::default())
            ->forProfileRequests($connector->getRegion());
    }
}
