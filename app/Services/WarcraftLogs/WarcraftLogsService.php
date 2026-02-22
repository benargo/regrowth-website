<?php

namespace App\Services\WarcraftLogs;

use App\Services\WarcraftLogs\Exceptions\GraphQLException;
use App\Services\WarcraftLogs\Exceptions\RateLimitedException;
use App\Services\WarcraftLogs\Traits\RateLimited;
use App\Traits\Cacheable;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

abstract class WarcraftLogsService
{
    use Cacheable;
    use RateLimited;

    protected const BASE_CACHE_KEY = 'warcraftlogs';

    protected const GRAPHQL_URL = 'https://www.warcraftlogs.com/api/v2/client';

    protected AuthenticationHandler $auth;

    protected int $guildId;

    protected int $timeout = 30;

    /**
     * @param  array{client_id: string, client_secret: string, guild_id?: int}  $config
     */
    public function __construct(array $config, AuthenticationHandler $auth)
    {
        if (empty($config)) {
            $config = config('services.warcraftlogs');
        }

        $this->guildId = $config['guild_id'] ?? 0;
        $this->auth = $auth;
    }

    /**
     * Get a configured HTTP client for GraphQL requests.
     */
    protected function http(?int $timeout = null): PendingRequest
    {
        return Http::baseUrl(self::GRAPHQL_URL)
            ->withToken($this->auth->clientToken())
            ->acceptJson()
            ->asJson()
            ->timeout($timeout ?? $this->timeout);
    }

    /**
     * Execute a GraphQL query and return the full HTTP response.
     * This method does not cache results.
     *
     * @param  array<string, mixed>  $variables
     * @return array<string, mixed>
     *
     * @throws GraphQLException
     * @throws RateLimitedException
     */
    protected function query(string $query, array $variables = [], ?int $ttl = null, ?int $timeout = null): array
    {
        $this->ensureNotRateLimited();

        return $this->cacheable(
            $this->queryCacheKey($query, $variables),
            $ttl ?? $this->cacheTtl, // Cache for 1 hour by default
            function () use ($query, $variables, $timeout) {
                $payload = ['query' => $query];

                if (! empty($variables)) {
                    $payload['variables'] = $variables;
                }

                try {
                    $response = $this->http($timeout)->post('', $payload);
                    $this->trackRateLimitHeaders($response);
                    $json = $response->throw()->json();
                } catch (RequestException $e) {
                    if ($e->response->status() === 429) {
                        $this->activateRateLimitCooldown();

                        throw new RateLimitedException;
                    }

                    throw $e;
                }

                if (isset($json['errors'])) {
                    throw new GraphQLException($json['errors']);
                }

                return $json['data'] ?? [];
            }
        );
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
}
