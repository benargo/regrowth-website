<?php

namespace App\Services\WarcraftLogs;

use App\Services\WarcraftLogs\Exceptions\GraphQLException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

abstract class WarcraftLogsService
{
    protected const BASE_CACHE_KEY = 'warcraftlogs.service';

    protected const DEFAULT_CACHE_TTL = 3600; // 1 hour

    protected AuthenticationHandler $authHandler;

    protected string $graphqlUrl;

    protected int $guildId;

    protected int $timeout = 30;

    protected bool $ignoreCache = false;

    protected int $cacheTtl = self::DEFAULT_CACHE_TTL;

    /**
     * @param  array{client_id: string, client_secret: string, token_url: string, graphql_url?: string, guild_id?: int, timeout?: int, cache_ttl?: int}  $config
     */
    public function __construct(array $config)
    {
        $this->authHandler = new AuthenticationHandler(
            $config['client_id'],
            $config['client_secret'],
            $config['token_url']
        );

        $this->graphqlUrl = $config['graphql_url'] ?? 'https://www.warcraftlogs.com/api/v2/client';
        $this->guildId = $config['guild_id'] ?? 0;
        $this->timeout = $config['timeout'] ?? $this->timeout;
        $this->cacheTtl = $config['cache_ttl'] ?? $this->cacheTtl;
    }

    /**
     * Get the authentication handler.
     */
    public function auth(): AuthenticationHandler
    {
        return $this->authHandler;
    }

    /**
     * Disable caching for the next query.
     */
    public function fresh(): static
    {
        $this->ignoreCache = true;

        return $this;
    }

    /**
     * Get a configured HTTP client for GraphQL requests.
     */
    protected function http(?int $timeout = null): PendingRequest
    {
        return Http::baseUrl($this->graphqlUrl)
            ->withToken($this->authHandler->clientToken())
            ->acceptJson()
            ->asJson()
            ->timeout($timeout ?? $this->timeout);
    }

    /**
     * Execute a GraphQL query and return the full HTTP response.
     * This method does not cache results.
     *
     * @param  array<string, mixed>  $variables
     */
    protected function query(string $query, array $variables = [], ?int $timeout = null): Response
    {
        $payload = ['query' => $query];

        if (! empty($variables)) {
            $payload['variables'] = $variables;
        }

        return $this->http($timeout)->post('', $payload)->throw();
    }

    /**
     * Execute a GraphQL query and return the data portion of the response.
     * Results are cached by default unless fresh() was called.
     *
     * @param  array<string, mixed>  $variables
     * @return array<string, mixed>
     *
     * @throws GraphQLException
     */
    protected function queryData(string $query, array $variables = [], ?int $timeout = null): array
    {
        if ($this->ignoreCache) {
            $this->ignoreCache = false;

            return $this->executeQuery($query, $variables, $timeout);
        }

        return Cache::remember(
            $this->queryCacheKey($query, $variables),
            $this->getCacheTtl(),
            fn () => $this->executeQuery($query, $variables, $timeout)
        );
    }

    /**
     * Execute the query and parse the response.
     *
     * @param  array<string, mixed>  $variables
     * @return array<string, mixed>
     *
     * @throws GraphQLException
     */
    protected function executeQuery(string $query, array $variables, ?int $timeout): array
    {
        $response = $this->query($query, $variables, $timeout);
        $json = $response->json();

        if (isset($json['errors']) && ! empty($json['errors'])) {
            throw new GraphQLException($json['errors']);
        }

        return $json['data'] ?? [];
    }

    /**
     * Generate a unique cache key for a GraphQL query.
     *
     * @param  array<string, mixed>  $variables
     */
    protected function queryCacheKey(string $query, array $variables): string
    {
        return static::BASE_CACHE_KEY.'.'.md5($query.json_encode($variables));
    }

    /**
     * Get the cache TTL in seconds.
     * Override this method in extending classes to customize the cache duration.
     */
    protected function getCacheTtl(): int
    {
        return $this->cacheTtl;
    }

    /**
     * Get the configured guild ID.
     */
    protected function getGuildId(): int
    {
        return $this->guildId;
    }
}
