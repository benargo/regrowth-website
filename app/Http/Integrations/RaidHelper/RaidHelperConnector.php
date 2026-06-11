<?php

namespace App\Http\Integrations\RaidHelper;

use Saloon\Contracts\Authenticator;
use Saloon\Http\Auth\TokenAuthenticator;
use Saloon\Http\Connector;
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

    public function __construct(
        protected string $token,
        protected string $serverId,
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
}
