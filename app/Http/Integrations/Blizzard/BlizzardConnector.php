<?php

namespace App\Http\Integrations\Blizzard;

use App\Http\Integrations\Blizzard\Exceptions\InvalidClassException;
use App\Http\Integrations\Blizzard\Exceptions\InvalidRaceException;
use App\Http\Integrations\Blizzard\Exceptions\ItemNotFoundException;
use App\Services\Blizzard\Exceptions\BlizzardApiException;
use App\Services\Blizzard\Exceptions\CharacterNotFoundException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
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
 * Saloon connector for the Blizzard Battle.net API.
 *
 * Handles base URL resolution per region, OAuth2 client-credentials authentication
 * (with cached token), automatic locale query parameter injection, rate limiting,
 * and translation of upstream errors into the App\Services\Blizzard\Exceptions\*
 * hierarchy that callers already catch.
 */
class BlizzardConnector extends Connector
{
    use AcceptsJson;
    use AlwaysThrowOnErrors;
    use ClientCredentialsBasicAuthGrant;
    use HasRateLimits;

    /** @var array<string, string> */
    private array $namespaces;

    public function __construct(
        protected string $clientId,
        protected string $clientSecret,
        protected GameVersion $gameVersion,
        protected Region $region,
        protected string $locale,
    ) {
        $component = $gameVersion->namespaceComponent();
        $regionValue = $region->value;

        $this->namespaces = [
            'profile' => "profile{$component}-{$regionValue}",
            'static' => "static{$component}-{$regionValue}",
            'dynamic' => "dynamic{$component}-{$regionValue}",
        ];

        if (! $this->region->supportsLocale($this->locale)) {
            throw new InvalidArgumentException(sprintf(
                'Locale "%s" is not supported for region "%s". Supported locales: %s',
                $this->locale,
                $this->region->value,
                implode(', ', $this->region->locales()),
            ));
        }
    }

    /**
     * The base URL is determined by the region, e.g. https://eu.api.blizzard.com for EU.
     */
    public function resolveBaseUrl(): string
    {
        return $this->region->apiUrl();
    }

    /**
     * Get the configured region.
     */
    public function getRegion(): Region
    {
        return $this->region;
    }

    /**
     * Get the configured locale.
     */
    public function getLocale(): string
    {
        return $this->locale;
    }

    /**
     * Get the namespace for a given kind (profile/static/dynamic).
     *
     * @throws InvalidArgumentException if the namespace kind is invalid.
     */
    public function namespace(string $kind): string
    {
        return $this->namespaces[$kind] ?? throw new InvalidArgumentException("Unknown Blizzard namespace kind: {$kind}");
    }

    /**
     * Default query parameters applied to every request.
     *
     * @return array<string, string>
     */
    protected function defaultQuery(): array
    {
        return ['locale' => $this->locale];
    }

    /**
     * Configure the OAuth2 client-credentials grant. The token endpoint lives on
     * battle.net, not the API host, so we allow a full URL override.
     */
    protected function defaultOauthConfig(): OAuthConfig
    {
        return OAuthConfig::make()
            ->setClientId($this->clientId)
            ->setClientSecret($this->clientSecret)
            ->setAllowBaseUrlOverride(true)
            ->setTokenEndpoint($this->region->tokenUrl());
    }

    /**
     * Authenticate every outgoing request with a cached OAuth2 access token.
     *
     * Token is cached against `blizzard:access_token:{region}` with tags
     * `['blizzard', 'api-auth']` so the rest of the app can flush it on demand.
     */
    public function boot(PendingRequest $pendingRequest): void
    {
        // Skip token request entirely if this *is* the token request, otherwise infinite loop.
        if ($pendingRequest->getRequest() instanceof GetClientCredentialsTokenBasicAuthRequest) {
            return;
        }

        $authenticator = $this->cachedAuthenticator();
        $pendingRequest->authenticate($authenticator);
    }

    /**
     * Get a cached AccessTokenAuthenticator or fetch a new token if expired or missing.
     */
    protected function cachedAuthenticator(): AccessTokenAuthenticator
    {
        // TODO:
        // v2 key prevents collision with the legacy App\Services\Blizzard\Client,
        // which writes a raw string at blizzard:access_token:{region} during the
        // migration window. Drop the suffix once the legacy Client is removed.
        $cacheKey = "blizzard:access_token:v2:{$this->region->value}";
        $store = Cache::tags(['blizzard', 'api-auth']);

        $cached = $store->get($cacheKey);

        if (is_array($cached) && isset($cached['token'], $cached['expires_at'])) {
            $expiresAt = new \DateTimeImmutable('@'.$cached['expires_at']);
            $authenticator = new AccessTokenAuthenticator($cached['token'], null, $expiresAt);

            if ($authenticator->hasNotExpired()) {
                return $authenticator;
            }
        }

        $authenticator = $this->getAccessToken();
        $expiresAt = $authenticator->getExpiresAt();
        $ttl = $expiresAt !== null ? max(60, $expiresAt->getTimestamp() - time()) : 3600;

        $store->put($cacheKey, [
            'token' => $authenticator->getAccessToken(),
            'expires_at' => $expiresAt?->getTimestamp() ?? (time() + 3600),
        ], $ttl);

        return $authenticator;
    }

    /**
     * Translate upstream Blizzard errors into our typed exception hierarchy so
     * existing `catch` blocks across the app keep working unchanged.
     */
    public function getRequestException(Response $response, ?Throwable $senderException): ?Throwable
    {
        $status = $response->status();
        $pendingRequest = $response->getPendingRequest();
        $path = parse_url($pendingRequest->getUrl(), PHP_URL_PATH) ?: $pendingRequest->getUrl();
        $method = $pendingRequest->getMethod()->value;

        $body = $response->json();
        $body = is_array($body) ? $body : null;
        $blizzardCode = is_array($body) ? Arr::get($body, 'type') : null;

        // 429s are handled by the rate-limit plugin (RateLimitReachedException).

        if ($status === 404 && $blizzardCode === 'BLZWEBAPI00000404') {
            if (str_starts_with($path, '/profile/wow/character/')) {
                return new CharacterNotFoundException($method, $path, $status, $response, $blizzardCode, $body, $senderException);
            }

            if (str_starts_with($path, '/data/wow/media/item/')) {
                return new ItemNotFoundException($method, $path, $status, $response, $blizzardCode, $body, $senderException);
            }

            if (str_starts_with($path, '/data/wow/item/')) {
                return new ItemNotFoundException($method, $path, $status, $response, $blizzardCode, $body, $senderException);
            }

            if (str_starts_with($path, '/data/wow/playable-race/')) {
                return new InvalidRaceException($method, $path, $status, $response, $blizzardCode, $body, $senderException);
            }

            if (str_starts_with($path, '/data/wow/playable-class/') || str_starts_with($path, '/data/wow/media/playable-class/')) {
                return new InvalidClassException($method, $path, $status, $response, $blizzardCode, $body, $senderException);
            }
        }

        return new BlizzardApiException($method, $path, $status, $response, $blizzardCode, $body, $senderException);
    }

    /**
     * Proactive rate limits. Blizzard's published quotas are 100 req/sec and
     * 36,000 req/hour per client. We stay under both.
     *
     * @return array<int, Limit>
     */
    protected function resolveLimits(): array
    {
        return [
            Limit::allow(100)->everySeconds(1),
            Limit::allow(36000)->everyHour(),
        ];
    }

    /**
     * Resolve the rate limit store.
     */
    protected function resolveRateLimitStore(): RateLimitStore
    {
        return new LaravelCacheStore(Cache::store());
    }
}
