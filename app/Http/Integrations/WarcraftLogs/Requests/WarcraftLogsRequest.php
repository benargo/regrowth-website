<?php

namespace App\Http\Integrations\WarcraftLogs\Requests;

use App\Http\Integrations\WarcraftLogs\Concerns\HasCaching;
use App\Http\Integrations\WarcraftLogs\WarcraftLogsNamespace;
use Saloon\CachePlugin\Contracts\Cacheable;
use Saloon\GraphQL\GraphQLRequest;

/**
 * Base for every Warcraft Logs GraphQL request.
 *
 * The namespace picks the host (one per game version), so the endpoint is an absolute
 * URL that overrides the connector's token host. Responses are cached per URL and body.
 * Each subclass declares cacheExpiryInSeconds().
 */
abstract class WarcraftLogsRequest extends GraphQLRequest implements Cacheable
{
    use HasCaching;

    /**
     * Saloon v4 refuses absolute endpoints unless the request opts in.
     */
    public ?bool $allowBaseUrlOverride = true;

    public function __construct(
        protected readonly WarcraftLogsNamespace $namespace,
    ) {}

    public function resolveEndpoint(): string
    {
        return $this->namespace->baseUrl();
    }

    public function namespace(): WarcraftLogsNamespace
    {
        return $this->namespace;
    }
}
