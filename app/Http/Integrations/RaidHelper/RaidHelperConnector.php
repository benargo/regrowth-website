<?php

namespace App\Http\Integrations\RaidHelper;

use Saloon\Contracts\Authenticator;
use Saloon\Http\Auth\TokenAuthenticator;
use Saloon\Http\Connector;
use Saloon\Http\Response;
use Saloon\RateLimitPlugin\Contracts\RateLimitStore;
use Saloon\RateLimitPlugin\Limit;
use Saloon\RateLimitPlugin\Stores\MemoryStore;
use Saloon\RateLimitPlugin\Traits\HasRateLimits;
use Saloon\Traits\Plugins\AcceptsJson;
use Saloon\Traits\Plugins\AlwaysThrowOnErrors;

/**
 * Saloon connector for the Raid Helper API (v4).
 *
 * Authenticates with a raw API token in the Authorization header (no Bearer prefix),
 * caches read responses for a short TTL. Upstream 4xx/5xx responses are surfaced as
 * Saloon's built-in exceptions. Pagination is handled by dedicated paginator classes.
 */
class RaidHelperConnector extends Connector
{
    use AcceptsJson;
    use AlwaysThrowOnErrors;
    use HasRateLimits;

    public function __construct(
        protected string $token,
        protected string $serverId,
        private ?RateLimitStore $store = null,
    ) {}

    /**
     * The single base URL for the Raid Helper API.
     */
    public function resolveBaseUrl(): string
    {
        return 'https://raid-helper.xyz/api/v4';
    }

    /**
     * Get the configured Discord server id.
     */
    public function serverId(): string
    {
        return $this->serverId;
    }

    /**
     * Authenticate with the raw token. The empty prefix produces an exact
     * `Authorization: {token}` header (no `Bearer`), matching the legacy client.
     */
    protected function defaultAuth(): Authenticator
    {
        return new TokenAuthenticator($this->token, prefix: '');
    }

    /**
     * No proactive limits — Raid Helper communicates limits dynamically via
     * response headers. Throttling is handled reactively in handleTooManyAttempts().
     *
     * @return array<int, Limit>
     */
    protected function resolveLimits(): array
    {
        return [];
    }

    /**
     * Resolve the rate limit store. Defaults to in-memory so tests get a fresh
     * store per connector instance. The service provider injects a persistent
     * LaravelCacheStore for production use.
     */
    protected function resolveRateLimitStore(): RateLimitStore
    {
        return $this->store ?? new MemoryStore;
    }

    /**
     * On a 429, mark the limit exceeded for the duration specified in Retry-After
     * (defaulting to 60 s) and throw RateLimitReachedException so the caller's
     * retry mechanism handles back-off.
     */
    protected function handleTooManyAttempts(Response $response, Limit $limit): void
    {
        if ($response->status() !== 429) {
            return;
        }

        $limit->exceeded(
            releaseInSeconds: (int) ($response->header('Retry-After') ?? 60),
        );
    }
}
