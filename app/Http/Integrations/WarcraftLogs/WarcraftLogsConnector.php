<?php

namespace App\Http\Integrations\WarcraftLogs;

use App\Http\Integrations\WarcraftLogs\Exceptions\ApiException;
use App\Http\Integrations\WarcraftLogs\Exceptions\GraphQLException;
use App\Http\Integrations\WarcraftLogs\Middleware\MonitorRateLimit;
use DateTimeImmutable;
use Illuminate\Support\Facades\Cache;
use Saloon\GraphQL\Traits\HandlesGraphQLErrors;
use Saloon\Helpers\OAuth2\OAuthConfig;
use Saloon\Http\Auth\AccessTokenAuthenticator;
use Saloon\Http\Connector;
use Saloon\Http\OAuth2\GetClientCredentialsTokenBasicAuthRequest;
use Saloon\Http\PendingRequest;
use Saloon\Http\Response;
use Saloon\RateLimitPlugin\Contracts\RateLimitStore;
use Saloon\RateLimitPlugin\Limit;
use Saloon\RateLimitPlugin\Stores\LaravelCacheStore;
use Saloon\RateLimitPlugin\Traits\HasRateLimits;
use Saloon\Traits\OAuth2\ClientCredentialsBasicAuthGrant;
use Saloon\Traits\Plugins\AcceptsJson;
use Saloon\Traits\Plugins\AlwaysThrowOnErrors;
use Throwable;

/**
 * Saloon connector for the Warcraft Logs v2 GraphQL API.
 *
 * Authenticates with OAuth2 client credentials against the www host (one token works
 * on every namespace host). Each request supplies its own namespace host as an absolute endpoint.
 */
class WarcraftLogsConnector extends Connector
{
    use AcceptsJson;
    use AlwaysThrowOnErrors;
    use ClientCredentialsBasicAuthGrant;
    use HandlesGraphQLErrors {
        getRequestException as getGraphQLRequestException;
    }
    use HasRateLimits;

    private const string TOKEN_CACHE_KEY = 'warcraftlogs:access_token';

    /**
     * The store is named $store because HasRateLimits already declares $rateLimitStore.
     */
    public function __construct(
        protected string $clientId,
        protected string $clientSecret,
        private readonly RateLimitResetCache $rateLimitReset,
        MonitorRateLimit $monitorRateLimit,
        private readonly ?RateLimitStore $store = null,
    ) {
        $this->middleware()->onResponse($monitorRateLimit, 'monitorRateLimit');
    }

    /**
     * The token host. GraphQL requests override this with their namespace host.
     */
    public function resolveBaseUrl(): string
    {
        return 'https://www.warcraftlogs.com';
    }

    protected function defaultOauthConfig(): OAuthConfig
    {
        return OAuthConfig::make()
            ->setClientId($this->clientId)
            ->setClientSecret($this->clientSecret)
            ->setTokenEndpoint('/oauth/token');
    }

    /**
     * Authenticate every request except the token request itself (which would loop).
     */
    public function boot(PendingRequest $pendingRequest): void
    {
        if ($pendingRequest->getRequest() instanceof GetClientCredentialsTokenBasicAuthRequest) {
            return;
        }

        $pendingRequest->authenticate($this->cachedAuthenticator());
    }

    /**
     * Reuse the cached token until it expires. The key and tags differ from the legacy
     * client's untagged `warcraftlogs:client_token`, so the two never clobber each other.
     */
    protected function cachedAuthenticator(): AccessTokenAuthenticator
    {
        $store = Cache::tags(['warcraftlogs', 'api-auth']);
        $cached = $store->get(self::TOKEN_CACHE_KEY);

        if (is_array($cached)) {
            $authenticator = new AccessTokenAuthenticator(
                $cached['token'],
                null,
                new DateTimeImmutable("@{$cached['expires_at']}"),
            );

            if ($authenticator->hasNotExpired()) {
                return $authenticator;
            }
        }

        $authenticator = $this->getAccessToken();
        $expiresAt = $authenticator->getExpiresAt() ?? now()->addHour()->toDateTimeImmutable();

        $store->put(
            self::TOKEN_CACHE_KEY,
            ['token' => $authenticator->getAccessToken(), 'expires_at' => $expiresAt->getTimestamp()],
            max(0, $expiresAt->getTimestamp() - now()->getTimestamp() - 30),
        );

        return $authenticator;
    }

    /**
     * GraphQL errors become the WCL GraphQLException (requests may narrow it further).
     * Every other failure, including any 4xx/5xx, becomes ApiException.
     * hasRequestFailed() comes from HandlesGraphQLErrors and never returns false.
     */
    public function getRequestException(Response $response, ?Throwable $senderException): ?Throwable
    {
        return $this->getGraphQLRequestException($response, $senderException)
            ?? new ApiException($response, previous: $senderException);
    }

    protected function createGraphQLException(Response $response, ?Throwable $senderException): GraphQLException
    {
        return new GraphQLException($response, previous: $senderException);
    }

    /**
     * WCL meters points per hour, not requests, so no request-count limit is declared:
     * one would also count cached responses and block traffic WCL never refused. The
     * plugin still adds its too-many-attempts limit, which handleTooManyAttempts()
     * arms on a 429.
     *
     * @return array<int, Limit>
     */
    protected function resolveLimits(): array
    {
        return [];
    }

    /**
     * The provider injects a tagged store, so the cooldown can be flushed on purpose.
     * The untagged fallback keeps tests on the array driver isolated.
     */
    protected function resolveRateLimitStore(): RateLimitStore
    {
        return $this->store ?? new LaravelCacheStore(Cache::store());
    }

    /**
     * On a 429, block requests until the cached points reset, or for an hour if none
     * is known. It never asks rateLimitData here, because that call would be refused too.
     */
    protected function handleTooManyAttempts(Response $response, Limit $limit): void
    {
        if ($response->status() !== 429) {
            return;
        }

        $limit->exceeded(releaseInSeconds: $this->rateLimitReset->secondsUntilReset() ?? 3600);
    }
}
