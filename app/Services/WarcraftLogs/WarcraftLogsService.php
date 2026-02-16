<?php

namespace App\Services\WarcraftLogs;

use App\Services\WarcraftLogs\Exceptions\GraphQLException;
use App\Services\WarcraftLogs\Exceptions\RateLimitedException;
use App\Traits\Cacheable;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

abstract class WarcraftLogsService
{
    use Cacheable;

    protected const BASE_CACHE_KEY = 'warcraftlogs';

    protected const GRAPHQL_URL = 'https://www.warcraftlogs.com/api/v2/client';

    protected const RATE_LIMIT_CACHE_KEY = 'warcraftlogs.rate_limited';

    protected const RATE_LIMIT_COOLDOWN = 3600; // 1 hour

    protected const RATE_LIMIT_INFO_CACHE_KEY = 'warcraftlogs.rate_limit';

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
     * Check if the API is currently rate limited and throw if so.
     *
     * @throws RateLimitedException
     */
    protected function ensureNotRateLimited(): void
    {
        if (Cache::has(self::RATE_LIMIT_CACHE_KEY)) {
            throw new RateLimitedException;
        }
    }

    /**
     * Activate the rate limit cooldown, preventing further requests for one hour.
     */
    protected function activateRateLimitCooldown(): void
    {
        Cache::put(self::RATE_LIMIT_CACHE_KEY, true, self::RATE_LIMIT_COOLDOWN);

        Log::warning('WarcraftLogs API rate limit exceeded. Pausing requests for one hour.');
    }

    /**
     * Track rate limit information from API response headers.
     */
    protected function trackRateLimitHeaders(Response $response): void
    {
        $limitHeader = $response->header('x-ratelimit-limit');
        $remainingHeader = $response->header('x-ratelimit-remaining');

        if ($limitHeader === '' || $remainingHeader === '') {
            return;
        }

        $limit = (int) $limitHeader;
        $remaining = (int) $remainingHeader;

        Cache::put(self::RATE_LIMIT_INFO_CACHE_KEY, [
            'limit' => $limit,
            'remaining' => $remaining,
        ], self::RATE_LIMIT_COOLDOWN);

        if ($limit > 0 && $remaining <= (int) ceil($limit * 0.1)) {
            Log::warning('WarcraftLogs API rate limit tokens running low.', [
                'remaining' => $remaining,
                'limit' => $limit,
            ]);
        }
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
